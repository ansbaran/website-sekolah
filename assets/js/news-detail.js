document.addEventListener("click", (event) => {
  const button = event.target.closest("[data-copy-current-url]");
  if (!button) return;

  const url = window.location.href;
  const originalText = button.textContent;

  navigator.clipboard.writeText(url)
    .then(() => {
      button.textContent = "Berhasil disalin!";
      window.setTimeout(() => {
        button.textContent = originalText;
      }, 2000);
    })
    .catch(() => {
      window.prompt("Salin URL berikut:", url);
    });
});
