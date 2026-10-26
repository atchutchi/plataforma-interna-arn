(() => {
  'use strict';
  const error = document.querySelector('.arn-ticket-errors');
  if (error) error.focus();
  document.querySelectorAll('.arn-tickets form[method="post"]').forEach(form => {
    form.addEventListener('submit', () => {
      form.querySelectorAll('button[type="submit"]').forEach(button => {
        button.disabled = true;
        button.textContent = 'A guardar…';
      });
    });
  });
  window.addEventListener('pageshow', event => {
    if (event.persisted) window.location.reload();
  });
})();
