"""Real renderer tests with generated fictional PDFs, no institutional originals."""
import hashlib
import io
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

from PIL import Image
from reportlab.pdfgen import canvas
from reportlab.lib.pdfencrypt import StandardEncryption

WORKER = Path(__file__).resolve().parents[2] / "wp-content/plugins/arn-intranet-core/src/Media/PdfWorker.py"


def fixture(size=(595, 842), encrypt=None, form=False, pages=2):
    data = io.BytesIO()
    pdf = canvas.Canvas(data, pagesize=size, encrypt=encrypt)
    for number in range(pages):
        pdf.setFillColorRGB(1 if number == 0 else 0, 0, 0 if number == 0 else 1)
        pdf.rect(0, 0, size[0], size[1], fill=1, stroke=0)
        pdf.setFillColorRGB(1, 1, 1)
        pdf.drawString(40, size[1] - 60, "ARN LAB - fictional page " + str(number + 1))
        if form:
            pdf.acroForm.textfield(name="fictional", x=40, y=100)
        pdf.showPage()
    pdf.save()
    return data.getvalue()


class PdfWorkerTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix="arn-pdf-test-")
        self.root = Path(self.temp.name).resolve()
        self.source = self.root / "source"

    def tearDown(self):
        self.temp.cleanup()

    def run_worker(self, data):
        self.source.write_bytes(data)
        result = subprocess.run([sys.executable, "-I", "-B", str(WORKER), str(self.source), str(self.root)],
                                capture_output=True, timeout=15)
        self.assertEqual(hashlib.sha256(data).digest(), hashlib.sha256(self.source.read_bytes()).digest())
        return result

    def test_static_first_page_with_exact_bounds_and_unchanged_original(self):
        result = self.run_worker(fixture())
        self.assertEqual((0, b"processed", b""), (result.returncode, result.stdout, result.stderr))
        for name, limit in (("thumbnail", 320), ("preview", 1280)):
            path = self.root / (name + ".png")
            with Image.open(path) as image:
                self.assertEqual("PNG", image.format)
                self.assertEqual(limit, max(image.size))
                self.assertEqual((255, 0, 0), image.getpixel((image.width // 2, image.height // 2)))
            self.assertLessEqual(path.stat().st_size, 1048576)

    def test_malformed_is_refused(self):
        self.assertNotEqual(0, self.run_worker(b"%PDF-1.4\ninvalid\n%%EOF").returncode)
        self.assertFalse((self.root / "preview.png").exists())

    def test_password_pdf_is_refused(self):
        self.assertNotEqual(0, self.run_worker(fixture(encrypt="fictional-secret")).returncode)
        self.assertFalse((self.root / "preview.png").exists())

    def test_encrypted_empty_password_is_also_refused(self):
        self.assertNotEqual(0, self.run_worker(fixture(encrypt=StandardEncryption("", ownerPassword="fictional"))).returncode)

    def test_interactive_form_is_refused(self):
        self.assertNotEqual(0, self.run_worker(fixture(form=True)).returncode)

    def test_excessive_page_dimensions_are_refused(self):
        self.assertNotEqual(0, self.run_worker(fixture(size=(20000, 842))).returncode)

    def test_input_limit_is_enforced(self):
        self.assertNotEqual(0, self.run_worker(fixture() + b" " * 1048576).returncode)

    def test_existing_output_is_never_overwritten(self):
        target = self.root / "thumbnail.png"
        target.write_bytes(b"preserve")
        self.assertNotEqual(0, self.run_worker(fixture()).returncode)
        self.assertEqual(b"preserve", target.read_bytes())

    def test_page_count_limit(self):
        self.assertNotEqual(0, self.run_worker(fixture(pages=501)).returncode)


if __name__ == "__main__":
    unittest.main()
