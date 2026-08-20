document.addEventListener('click', (event) => {
  const link = event.target.closest('[data-ccl-confirm]');
  if (!link) return;
  if (!window.confirm(link.dataset.cclConfirm || '¿Continuar?')) event.preventDefault();
});
