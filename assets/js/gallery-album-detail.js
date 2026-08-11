const GRID_SELECTOR = "[data-album-lightbox-grid]";
const ITEM_SELECTOR = "[data-album-lightbox-item]";
const IMAGE_EXTENSIONS = /\.(jpe?g|png|webp|gif)(\?.*)?$/i;

function onReady(callback) {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", callback, { once: true });
    return;
  }

  callback();
}

function normalizeText(value, fallback = "Foto album galeri") {
  const text = String(value || "").replace(/\s+/g, " ").trim();
  return text || fallback;
}

function safeImageUrl(value) {
  if (!value) return "";

  try {
    const url = new URL(value, window.location.href);
    if (url.protocol !== "http:" && url.protocol !== "https:") {
      return "";
    }

    if (!IMAGE_EXTENSIONS.test(url.pathname)) {
      return "";
    }

    return url.href;
  } catch (error) {
    return "";
  }
}

function createButton(className, label, text) {
  const button = document.createElement("button");
  button.type = "button";
  button.className = `album-lightbox__button ${className}`;
  button.setAttribute("aria-label", label);
  button.textContent = text;
  return button;
}

function createLightbox() {
  const root = document.createElement("div");
  root.className = "album-lightbox";
  root.hidden = true;
  root.setAttribute("role", "dialog");
  root.setAttribute("aria-modal", "true");
  root.setAttribute("aria-labelledby", "album-lightbox-title");
  root.setAttribute("aria-describedby", "album-lightbox-description");

  const dialog = document.createElement("div");
  dialog.className = "album-lightbox__dialog";
  dialog.tabIndex = -1;

  const topbar = document.createElement("div");
  topbar.className = "album-lightbox__topbar";

  const counter = document.createElement("p");
  counter.className = "album-lightbox__counter";
  counter.setAttribute("aria-live", "polite");

  const closeButton = createButton("album-lightbox__close", "Tutup tampilan gambar", "x");

  const media = document.createElement("div");
  media.className = "album-lightbox__media";

  const image = document.createElement("img");
  image.className = "album-lightbox__image";
  image.alt = "";
  image.decoding = "async";

  const prevButton = createButton("album-lightbox__nav album-lightbox__prev", "Lihat foto sebelumnya", "<");
  const nextButton = createButton("album-lightbox__nav album-lightbox__next", "Lihat foto berikutnya", ">");

  const caption = document.createElement("div");
  caption.className = "album-lightbox__caption";

  const title = document.createElement("h2");
  title.id = "album-lightbox-title";

  const description = document.createElement("p");
  description.id = "album-lightbox-description";

  topbar.appendChild(counter);
  topbar.appendChild(closeButton);
  media.appendChild(image);
  caption.appendChild(title);
  caption.appendChild(description);
  dialog.appendChild(topbar);
  dialog.appendChild(media);
  dialog.appendChild(prevButton);
  dialog.appendChild(nextButton);
  dialog.appendChild(caption);
  root.appendChild(dialog);
  document.body.appendChild(root);

  return {
    root,
    dialog,
    image,
    title,
    description,
    counter,
    closeButton,
    prevButton,
    nextButton,
  };
}

function initAlbumLightbox() {
  const grid = document.querySelector(GRID_SELECTOR);
  if (!grid || grid.dataset.albumLightboxReady === "true") return;

  grid.dataset.albumLightboxReady = "true";
  const lightbox = createLightbox();
  const focusableSelector = "button:not([disabled]):not([hidden]), [href], [tabindex]:not([tabindex='-1'])";
  let items = [];
  let activeIndex = 0;
  let activeTrigger = null;
  let previousModalOpen = false;

  const collectItems = () => Array.from(grid.querySelectorAll(ITEM_SELECTOR))
    .map((button) => ({
      button,
      src: safeImageUrl(button.dataset.albumLightboxSrc),
      title: normalizeText(button.dataset.albumLightboxTitle),
      caption: normalizeText(button.dataset.albumLightboxCaption, "SD Cahaya Harapan Bekasi"),
    }))
    .filter((item) => item.src);

  const focusableElements = () => Array.from(lightbox.root.querySelectorAll(focusableSelector))
    .filter((element) => element.offsetParent !== null);

  const render = () => {
    const item = items[activeIndex];
    if (!item) return;

    lightbox.image.src = item.src;
    lightbox.image.alt = item.title;
    lightbox.title.textContent = item.title;
    lightbox.description.textContent = item.caption;
    lightbox.counter.textContent = `${activeIndex + 1} / ${items.length}`;

    const singleItem = items.length <= 1;
    lightbox.prevButton.disabled = singleItem;
    lightbox.nextButton.disabled = singleItem;
  };

  const move = (step) => {
    if (items.length <= 1) return;
    activeIndex = (activeIndex + step + items.length) % items.length;
    render();
    lightbox.dialog.focus({ preventScroll: true });
  };

  const close = () => {
    if (lightbox.root.hidden) return;

    lightbox.root.hidden = true;
    lightbox.image.removeAttribute("src");
    document.documentElement.classList.remove("album-lightbox-open");
    document.body.classList.remove("album-lightbox-open");
    if (!previousModalOpen) {
      document.body.classList.remove("modal-open");
    }

    const trigger = activeTrigger;
    activeTrigger = null;
    trigger?.focus?.({ preventScroll: true });
  };

  const open = (button) => {
    items = collectItems();
    activeIndex = items.findIndex((item) => item.button === button);
    if (activeIndex < 0) return;

    activeTrigger = button;
    previousModalOpen = document.body.classList.contains("modal-open");
    render();
    document.documentElement.classList.add("album-lightbox-open");
    document.body.classList.add("album-lightbox-open", "modal-open");
    lightbox.root.hidden = false;
    lightbox.dialog.focus({ preventScroll: true });
  };

  grid.addEventListener("click", (event) => {
    const button = event.target.closest(ITEM_SELECTOR);
    if (!button || !grid.contains(button)) return;
    open(button);
  });

  lightbox.closeButton.addEventListener("click", close);
  lightbox.prevButton.addEventListener("click", () => move(-1));
  lightbox.nextButton.addEventListener("click", () => move(1));

  lightbox.root.addEventListener("click", (event) => {
    if (event.target === lightbox.root) {
      close();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (lightbox.root.hidden) return;

    if (event.key === "Escape") {
      event.preventDefault();
      close();
      return;
    }

    if (event.key === "ArrowLeft") {
      event.preventDefault();
      move(-1);
      return;
    }

    if (event.key === "ArrowRight") {
      event.preventDefault();
      move(1);
      return;
    }

    if (event.key !== "Tab") return;

    const focusables = focusableElements();
    if (focusables.length === 0) {
      event.preventDefault();
      lightbox.dialog.focus({ preventScroll: true });
      return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (!lightbox.root.contains(document.activeElement) || document.activeElement === lightbox.dialog) {
      event.preventDefault();
      (event.shiftKey ? last : first).focus({ preventScroll: true });
    } else if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus({ preventScroll: true });
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus({ preventScroll: true });
    }
  });
}

onReady(initAlbumLightbox);
