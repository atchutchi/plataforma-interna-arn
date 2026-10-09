document.addEventListener('click', (event) => {
  const trigger = event.target instanceof Element
    ? event.target.closest('[data-arn-skip]')
    : null;

  if (!trigger) {
    return;
  }

  const target = document.getElementById('conteudo');

  if (!target) {
    return;
  }

  event.preventDefault();
  target.setAttribute('tabindex', '-1');
  target.focus();
});
