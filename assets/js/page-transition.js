const SAME_PAGE_TARGETS = new Set(["", "#"]);
const TRANSITION_DELAY = 180;

function isModifiedClick(event) {
  return event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
}

function shouldTransition(link) {
  const href = link.getAttribute("href");

  if (!href || SAME_PAGE_TARGETS.has(href) || href.startsWith("#")) return false;
  if (link.target && link.target !== "_self") return false;
  if (link.hasAttribute("download")) return false;

  const url = new URL(link.href, window.location.href);

  return url.origin === window.location.origin && url.pathname !== window.location.pathname;
}

export function initPageTransitions() {
  window.addEventListener("pageshow", () => {
    document.body.classList.remove("page-is-leaving");
  });

  document.addEventListener("click", (event) => {
    const link = event.target.closest("a[href]");

    if (!link || isModifiedClick(event) || !shouldTransition(link)) return;

    event.preventDefault();
    document.body.classList.add("page-is-leaving");

    window.setTimeout(() => {
      window.location.href = link.href;
    }, TRANSITION_DELAY);
  });
}
