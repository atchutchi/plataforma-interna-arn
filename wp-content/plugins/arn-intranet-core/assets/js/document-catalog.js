(() => {
  const forms = document.querySelectorAll('.arn-document-form');
  const bytes = new TextEncoder();
  for (const form of forms) {
    const recipients = [...form.querySelectorAll('input[name="recipient_ids[]"]')];
    const dates = [form.elements.namedItem('valid_from'), form.elements.namedItem('valid_until')];
    const summary = form.elements.namedItem('summary');
    const reason = form.elements.namedItem('reason');
    const validate = () => {
      if (recipients.length) {
        recipients[0].setCustomValidity(recipients.some(input => input.checked) ? '' : 'Selecciona pelo menos um destinatário.');
      }
      if (dates[1]) {
        dates[1].setCustomValidity(dates[0].value && dates[1].value && dates[1].value < dates[0].value ? 'A data final não pode ser anterior à data inicial.' : '');
      }
      if (summary) {
        summary.setCustomValidity(/[\r\n]/.test(summary.value) ? 'O resumo deve ficar numa única linha.' : '');
      }
      if (reason) {
        reason.setCustomValidity(bytes.encode(reason.value.trim()).length > 500 ? 'Encurta a justificação.' : '');
      }
    };
    form.addEventListener('change', validate);
    form.addEventListener('input', validate);
    form.addEventListener('submit', event => {
      validate();
      if (!form.reportValidity()) event.preventDefault();
    });
  }
})();
