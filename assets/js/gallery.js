export function initGalleryFilters() {
  const filterGroup = document.querySelector("[data-gallery-filters]");
  const items = document.querySelectorAll("[data-gallery-item]");

  if (!filterGroup || !items.length) return;

  const buttons = filterGroup.querySelectorAll("[data-filter]");

  filterGroup.addEventListener("click", (event) => {
    const button = event.target.closest("[data-filter]");

    if (!button) return;

    const selectedFilter = button.dataset.filter;

    buttons.forEach((item) => {
      item.classList.toggle("active", item === button);
      item.setAttribute("aria-pressed", String(item === button));
    });

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
