(function () {
  const detail = document.querySelector('[data-agenda-detail]');
  if (!detail) return;

  const copyButton = detail.querySelector('[data-copy-link]');
  const printButton = detail.querySelector('[data-print-agenda]');

  if (copyButton) {
    copyButton.addEventListener('click', async function () {
      try {
        await navigator.clipboard.writeText(window.location.href);
        copyButton.classList.add('is-copied');
        copyButton.setAttribute('aria-label', 'Link tersalin');
        setTimeout(function () {
          copyButton.classList.remove('is-copied');
          copyButton.setAttribute('aria-label', 'Salin link');
        }, 1800);
      } catch (error) {
        window.prompt('Salin link agenda:', window.location.href);
      }
    });
  }

  if (printButton) {
    printButton.addEventListener('click', function () {
      window.print();
    });
  }
})();