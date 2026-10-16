/* Error recovery stays in this response. No browser storage or background write. */
(() => {
  const summary = document.getElementById('f112-errors');
  if (!summary) return;
  document.querySelectorAll('.arn-f112-field-error').forEach(error => {
    const field = document.getElementById(error.id.replace('f112-error-', 'f112-'));
    if (!field) return;
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', `${field.getAttribute('aria-describedby') || ''} ${error.id}`.trim());
  });
  summary.focus();
})();
