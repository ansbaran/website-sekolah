const LIGHTBOX_READY = "galleryLightboxReady";
const CARD_SELECTOR = "[data-gallery-item]";
const IMAGE_EXTENSIONS = /\.(jpe?g|png|webp|gif)(\?.*)?$/i;

function onReady(callback) {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", callback, { once: true });
    return;
  }

  callback();
}

function normalizeText(value, fallback = "Galeri SD Cahaya Harapan Bekasi") {
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
  button.className = `gallery-lightbox__button ${className}`;
  button.setAttribute("aria-label", label);
  button.textContent = text;
  return button;
}

function createLightbox() {
  const root = document.createElement("div");
  root.className = "gallery-lightbox";
  root.hidden = true;
  root.setAttribute("role", "dialog");
  root.setAttribute("aria-modal", "true");
  root.setAttribute("aria-labelledby", "gallery-lightbox-title");
  root.setAttribute("aria-describedby", "gallery-lightbox-description");

  const dialog = document.createElement("div");
  dialog.className = "gallery-lightbox__dialog";
  dialog.tabIndex = -1;

  const counter = document.createElement("p");
  counter.className = "gallery-lightbox__count";
  counter.setAttribute("aria-live", "polite");

  const closeButton = createButton("gallery-lightbox__close", "Tutup tampilan gambar", "x");
  const prevButton = createButton("gallery-lightbox__prev", "Lihat gambar sebelumnya", "<");
  const nextButton = createButton("gallery-lightbox__next", "Lihat gambar berikutnya", ">");

  const media = document.createElement("div");
  media.className = "gallery-lightbox__media";

  const image = document.createElement("img");
  image.className = "gallery-lightbox__image";
  image.alt = "";
  image.decoding = "async";

  const caption = document.createElement("div");
  caption.className = "gallery-lightbox__caption";

  const title = document.createElement("h2");
  title.id = "gallery-lightbox-title";

  const description = document.createElement("p");
  description.id = "gallery-lightbox-description";

  media.appendChild(image);
  caption.appendChild(title);
  caption.appendChild(description);
  dialog.appendChild(counter);
  dialog.appendChild(closeButton);
  dialog.appendChild(prevButton);
  dialog.appendChild(nextButton);
  dialog.appendChild(media);
  dialog.appendChild(caption);
  root.appendChild(dialog);
  document.body.appendChild(root);

  return { root, dialog, image, title, description, counter, closeButton, prevButton, nextButton };
}

function initGalleryLightbox() {
  const grid = document.querySelector("[data-gallery-grid]");
  if (!grid || grid.dataset[LIGHTBOX_READY] === "true") return;

  grid.dataset[LIGHTBOX_READY] = "true";
  const lightbox = createLightbox();
  let items = [];
  let activeIndex = 0;
  let activeTrigger = null;
  let previousModalOpen = false;

  const getCardTitle = (card) => normalizeText(card.querySelector("h3")?.textContent || card.querySelector("img")?.alt);
  const getCardCategory = (card) => normalizeText(card.querySelector("figcaption p")?.textContent, "Galeri sekolah");

  const enhanceCards = () => {
    grid.querySelectorAll(CARD_SELECTOR).forEach((card) => {
      const title = getCardTitle(card);
      card.tabIndex = 0;
      card.setAttribute("role", "button");
      card.setAttribute("aria-label", `Lihat gambar: ${title}`);
    });
  };

  const getVisibleItems = () => Array.from(grid.querySelectorAll(CARD_SELECTOR))
    .filter((card) => !card.hidden && card.offsetParent !== null)
    .map((card) => {
      const image = card.querySelector("img");
      return {
        card,
        src: safeImageUrl(image?.currentSrc || image?.src),
        title: getCardTitle(card),
        category: getCardCategory(card),
      };
    })
    .filter((item) => item.src);

  const focusableSelector = "button:not([disabled]):not([hidden]), [href], [tabindex]:not([tabindex='-1'])";
  const focusableElements = () => Array.from(lightbox.root.querySelectorAll(focusableSelector))
    .filter((element) => element.offsetParent !== null);

  const renderItem = () => {
    const item = items[activeIndex];
    if (!item) return;

    lightbox.image.src = item.src;
    lightbox.image.alt = item.title;
    lightbox.title.textContent = item.title;
    lightbox.description.textContent = item.category;
    lightbox.counter.textContent = `${activeIndex + 1} / ${items.length}`;

    const singleItem = items.length <= 1;
    lightbox.prevButton.disabled = singleItem;
    lightbox.nextButton.disabled = singleItem;
    lightbox.prevButton.hidden = singleItem;
    lightbox.nextButton.hidden = singleItem;
  };

  const move = (step) => {
    if (items.length <= 1) return;
    activeIndex = (activeIndex + step + items.length) % items.length;
    renderItem();
    lightbox.dialog.focus({ preventScroll: true });
  };

  const close = () => {
    if (lightbox.root.hidden) return;

    lightbox.root.hidden = true;
    lightbox.image.removeAttribute("src");
    document.documentElement.classList.remove("gallery-lightbox-open");
    document.body.classList.remove("gallery-lightbox-open");
    if (!previousModalOpen) {
      document.body.classList.remove("modal-open");
    }

    const trigger = activeTrigger;
    activeTrigger = null;
    trigger?.focus?.({ preventScroll: true });
  };

  const open = (card) => {
    items = getVisibleItems();
    activeIndex = items.findIndex((item) => item.card === card);
    if (activeIndex < 0) return;

    activeTrigger = card;
    previousModalOpen = document.body.classList.contains("modal-open");
    renderItem();
    document.documentElement.classList.add("gallery-lightbox-open");
    document.body.classList.add("gallery-lightbox-open", "modal-open");
    lightbox.root.hidden = false;
    lightbox.dialog.focus({ preventScroll: true });
  };

  grid.addEventListener("click", (event) => {
    const card = event.target.closest(CARD_SELECTOR);
    if (!card || !grid.contains(card)) return;
    open(card);
  });

  grid.addEventListener("keydown", (event) => {
    const card = event.target.closest(CARD_SELECTOR);
    if (!card || !grid.contains(card)) return;

    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      open(card);
    }
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

  const observer = new MutationObserver(enhanceCards);
  observer.observe(grid, { childList: true });
  enhanceCards();
}

onReady(initGalleryLightbox);