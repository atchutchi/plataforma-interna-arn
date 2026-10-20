(function () {
  'use strict';
  const errors = document.getElementById('arn-task-errors');
  if (errors) errors.focus();
  document.querySelectorAll('form[data-task-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.getAttribute('aria-busy') === 'true') {
        event.preventDefault();
        return;
      }
      form.setAttribute('aria-busy', 'true');
      const button = event.submitter;
      if (button) {
        button.dataset.taskLabel = button.textContent;
        button.disabled = true;
        button.textContent = 'A guardar…';
      }
    });
  });
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-task-form]').forEach(function (form) {
      form.removeAttribute('aria-busy');
      form.querySelectorAll('button[data-task-label]').forEach(function (button) {
        button.disabled = false;
        button.textContent = button.dataset.taskLabel;
        delete button.dataset.taskLabel;
      });
    });
  });
})();
