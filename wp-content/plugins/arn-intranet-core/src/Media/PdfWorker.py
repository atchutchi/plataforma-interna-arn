"""Private static PDF first page. CLI only, no forms/JS initialization or rewrites."""

import ctypes
import importlib.metadata
import io
import math
import os
from pathlib import Path
import stat
import sys

MAX_BYTES = 1048576
_job_handle = None


def resource_limits():
    """Fail closed when the host cannot enforce CPU and memory limits."""
    global _job_handle
    if os.name == "nt":
        from ctypes import wintypes as w

        class Basic(ctypes.Structure):
            _fields_ = [("process_time", ctypes.c_longlong), ("job_time", ctypes.c_longlong),
                        ("flags", w.DWORD), ("min_ws", ctypes.c_size_t),
                        ("max_ws", ctypes.c_size_t), ("active", w.DWORD),
                        ("affinity", ctypes.c_size_t), ("priority", w.DWORD),
                        ("scheduling", w.DWORD)]

        class Extended(ctypes.Structure):
            _fields_ = [("basic", Basic), ("io", ctypes.c_ulonglong * 6),
                        ("process_memory", ctypes.c_size_t), ("job_memory", ctypes.c_size_t),
                        ("peak_process", ctypes.c_size_t), ("peak_job", ctypes.c_size_t)]

        kernel = ctypes.WinDLL("kernel32", use_last_error=True)
        kernel.CreateJobObjectW.argtypes = [ctypes.c_void_p, w.LPCWSTR]
        kernel.CreateJobObjectW.restype = w.HANDLE
        kernel.SetInformationJobObject.argtypes = [w.HANDLE, ctypes.c_int, ctypes.c_void_p, w.DWORD]
        kernel.AssignProcessToJobObject.argtypes = [w.HANDLE, w.HANDLE]
        kernel.GetCurrentProcess.restype = w.HANDLE
        _job_handle = kernel.CreateJobObjectW(None, None)
        limits = Extended()
        limits.basic.flags = 0x100 | 0x8 | 0x2  # process memory, active process, CPU time
        limits.basic.process_time = 8 * 10000000
        limits.basic.active = 1
        limits.process_memory = 384 * 1024 * 1024
        if not _job_handle or not kernel.SetInformationJobObject(_job_handle, 9, ctypes.byref(limits), ctypes.sizeof(limits)):
            raise ValueError("limits")
        if not kernel.AssignProcessToJobObject(_job_handle, kernel.GetCurrentProcess()):
            raise ValueError("limits")
    elif os.name == "posix":
        import resource
        resource.setrlimit(resource.RLIMIT_AS, (384 * 1024 * 1024,) * 2)
        resource.setrlimit(resource.RLIMIT_CPU, (8, 8))
        resource.setrlimit(resource.RLIMIT_FSIZE, (MAX_BYTES, MAX_BYTES))
    else:
        raise ValueError("host")


def regular(path):
    info = path.lstat()
    if path.is_symlink() or not stat.S_ISREG(info.st_mode) or info.st_nlink != 1:
        raise ValueError("source")
    return info


def render(source, directory):
    resource_limits()
    for name, version in (("pypdfium2", "5.14.0"), ("Pillow", "12.3.0")):
        if importlib.metadata.version(name) != version:
            raise ValueError("runtime")
    import pypdfium2 as pdfium
    import pypdfium2.raw as raw
    from PIL import Image

    root = Path(directory)
    path = Path(source)
    if not root.is_absolute() or root.is_symlink() or root.resolve() != root or not root.is_dir():
        raise ValueError("directory")
    if path != root / "source" or regular(path).st_size not in range(1, MAX_BYTES + 1):
        raise ValueError("source")
    outputs = [root / "thumbnail.png", root / "preview.png"]
    if any(p.exists() or p.is_symlink() for p in outputs):
        raise ValueError("existing output")
    data = path.read_bytes()
    if len(data) > MAX_BYTES or not data.startswith(b"%PDF-"):
        raise ValueError("pdf")
    with pdfium.PdfDocument(data) as document:
        # An empty password can open encrypted PDFs. Explicitly reject those as well.
        if raw.FPDF_GetSecurityHandlerRevision(document) >= 0 or raw.FPDF_GetFormType(document) != 0:
            raise ValueError("encrypted or interactive")
        if not 1 <= len(document) <= 500:
            raise ValueError("pages")
        page = document[0]
        try:
            width, height = page.get_size()
            if not all(math.isfinite(n) and 1 <= n <= 14400 for n in (width, height)):
                raise ValueError("dimensions")
            bitmap = page.render(scale=1280 / max(width, height), may_draw_forms=False, draw_annots=True)
            try:
                preview = bitmap.to_pil().convert("RGB")
                preview.thumbnail((1280, 1280), Image.Resampling.LANCZOS)
            finally:
                bitmap.close()
        finally:
            page.close()
    # All bytes are checked before exclusive writes. The parent scans each resulting PNG.
    encoded = []
    for limit in (320, 1280):
        image = preview.copy()
        image.thumbnail((limit, limit), Image.Resampling.LANCZOS)
        buffer = io.BytesIO()
        image.save(buffer, format="PNG", compress_level=6)
        value = buffer.getvalue()
        if not 0 < len(value) <= MAX_BYTES:
            raise ValueError("output limit")
        encoded.append(value)
    for target, value in zip(outputs, encoded):
        with target.open("xb") as stream:
            stream.write(value)
            stream.flush()
            os.fsync(stream.fileno())


if __name__ == "__main__":
    try:
        if len(sys.argv) != 3:
            raise ValueError("arguments")
        render(sys.argv[1], sys.argv[2])
        print("processed", end="")
    except Exception:
        # No document content, paths, passwords or decoder diagnostics on stdout.
        sys.exit(1)
