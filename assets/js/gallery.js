export function initGalleryFilters() {
  const filterGroup = document.querySelector("[data-gallery-filters]");
  const items = document.querySelectorAll("[data-gallery-item]");

  if (!filterGroup || !items.length) return;

  const buttons = filterGroup.querySelectorAll("[data-filter]");
  const activeClasses = [
    "text-white",
    "shadow-lg",
    "shadow-[#0D0B61]/20",
    "scale-[1.03]",
    "-translate-y-0.5"
  ];

  const inactiveClasses = [
    "text-slate-600",
    "shadow-sm"
  ];

  function syncButtonState(activeButton) {
    buttons.forEach((item) => {
      const isActive = item === activeButton;

      item.classList.toggle("active", isActive);
      item.setAttribute("aria-pressed", String(isActive));

      item.classList.toggle("bg-gradient-to-r", isActive);
      item.classList.toggle("from-[#0D0B61]", isActive);
      item.classList.toggle("to-[#478B8D]", isActive);
      item.classList.toggle("border-transparent", isActive);

      activeClasses.forEach((className) => item.classList.toggle(className, isActive));
      inactiveClasses.forEach((className) => item.classList.toggle(className, !isActive));
    });
  }

  syncButtonState(filterGroup.querySelector("[aria-pressed='true']") || buttons[0]);

  filterGroup.addEventListener("click", (event) => {
    const button = event.target.closest("[data-filter]");

    if (!button) return;

    const selectedFilter = button.dataset.filter;

    syncButtonState(button);

    items.forEach((item) => {
      const isVisible =
        selectedFilter === "semua" || item.dataset.category === selectedFilter;

      item.hidden = !isVisible;

      if (isVisible) {
        item.classList.remove("active");
        requestAnimationFrame(() => item.classList.add("active"));
      }
    });
  });
}
