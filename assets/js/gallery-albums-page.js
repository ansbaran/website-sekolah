const API_ENDPOINT = "api/public-gallery-albums.php";
const ALBUM_DETAIL_ENDPOINT = "api/public-gallery-album.php";
const DEFAULT_LIMIT = 12;
const ALBUM_PHOTOS_PER_PAGE = 12;
const META_SEPARATOR = "\u2022";
const FILTER_CATEGORIES = {
  semua: null,
  fasilitas: "Fasilitas",
  kegiatan: "Kegiatan",
  prestasi: "Prestasi",
  ekstrakurikuler: "Ekstrakurikuler",
  akademik: "Akademik"
};

const state = {
  category: "semua",
  page: 1,
  limit: DEFAULT_LIMIT,
  controller: null,
  listScrollY: 0,
  lastAlbumCard: null,
  view: "list"
};

const albumState = {
  controller: null,
  token: 0,
  title: "",
  category: "",
  photos: [],
  page: 1,
  activeIndex: 0
};

const lightboxState = {
  elements: null,
  opener: null,
  scrollY: 0,
  isOpen: false
};

const selectors = {
  section: ".gallery-section",
  filters: "[data-gallery-filters]",
  grid: "[data-gallery-grid]",
  loading: "[data-gallery-loading]",
  empty: "[data-gallery-empty]",
  pagination: "[data-gallery-pagination]",
  detail: "[data-album-detail]",
  detailBack: "[data-album-detail-back]",
  detailTitle: "[data-album-detail-title]",
  detailMeta: "[data-album-detail-meta]",
  detailLoading: "[data-album-detail-loading]",
  detailStatus: "[data-album-detail-status]",
  detailGrid: "[data-album-detail-grid]",
  detailPagination: "[data-album-detail-pagination]"
};

function setHidden(element, hidden) {
  if (!element) return;
  element.classList.toggle("hidden", hidden);
  element.hidden = hidden;
  element.setAttribute("aria-hidden", String(hidden));
  if ("inert" in element) element.inert = hidden;
}

function createElement(tagName, className, text = "") {
  const element = document.createElement(tagName);
  if (className) element.className = className;
  if (text) element.textContent = text;
  return element;
}

function normalizeText(value, fallback = "") {
  const text = String(value ?? "").replace(/\s+/g, " ").trim();
  return text || fallback;
}

function formatPhotoCount(value) {
  const count = Number.parseInt(value, 10);
  const safeCount = Number.isFinite(count) && count >= 0 ? count : 0;
  return `${safeCount} foto`;
}

function formatDate(value) {
  const raw = normalizeText(value);
  if (!raw) return "";

  const date = new Date(`${raw}T00:00:00`);
  if (Number.isNaN(date.getTime())) return "";

  return new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "long",
    year: "numeric"
  }).format(date);
}

function safeRelativePath(value) {
  const path = String(value ?? "").replace(/\\/g, "/").trim();
  if (
    !path ||
    path.includes("\0") ||
    /^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(path) ||
    /(?:^|\/)\.\.(?:\/|$)/.test(path)
  ) {
    return "";
  }

  return path.replace(/^\/+/, "");
}

function buildApiUrl() {
  const url = new URL(API_ENDPOINT, window.location.href);
  url.searchParams.set("page", String(state.page));
  url.searchParams.set("limit", String(state.limit));

  const category = FILTER_CATEGORIES[state.category];
  if (category) url.searchParams.set("category", category);

  return url;
}

function buildAlbumDetailUrl(slug) {
  const url = new URL(ALBUM_DETAIL_ENDPOINT, window.location.href);
  url.searchParams.set("slug", slug);
  return url;
}

function syncFilterButtons(filterGroup) {
  filterGroup.querySelectorAll("[data-filter]").forEach((button) => {
    const isActive = button.dataset.filter === state.category;
    button.classList.toggle("active", isActive);
    button.setAttribute("aria-pressed", String(isActive));
  });
}

function setLoading(elements, isLoading) {
  setHidden(elements.loading, !isLoading);
  elements.grid?.classList.toggle("is-loading", isLoading);
  elements.grid?.setAttribute("aria-busy", String(isLoading));
}

function showMessage(elements, message, isError = false) {
  if (!elements.empty) return;
  elements.empty.textContent = message;
  elements.empty.classList.toggle("is-error", isError);
  setHidden(elements.empty, false);
}

function createImageFallback(album) {
  const fallback = createElement("span", "album-card__image-fallback");
  fallback.setAttribute("aria-hidden", "true");
  fallback.textContent = normalizeText(album.title, "Album").slice(0, 1).toUpperCase();
  return fallback;
}

function createAlbumCard(album) {
  const title = normalizeText(album.title, "Album Galeri");
  const slug = normalizeText(album.slug);
  const category = normalizeText(album.category, "Galeri");
  const photoCount = formatPhotoCount(album.photo_count);
  const imagePath = safeRelativePath(album.cover_image);
  const alt = normalizeText(album.cover_alt, title);

  const card = document.createElement("button");
  card.type = "button";
  card.className = "simple-gallery-card album-card gallery-card-fade";
  card.dataset.albumCard = "";
  card.dataset.slug = slug;
  card.disabled = !slug;
  card.setAttribute("aria-label", `Buka album ${title}, ${photoCount}`);

  const media = createElement("span", "album-card__media");
  const fallback = createImageFallback(album);

  if (imagePath) {
    const image = document.createElement("img");
    image.src = imagePath;
    image.alt = alt;
    image.loading = "lazy";
    image.decoding = "async";
    image.addEventListener("error", () => {
      image.hidden = true;
      fallback.hidden = false;
      card.classList.add("is-image-missing");
    }, { once: true });
    fallback.hidden = true;
    media.append(image, fallback);
  } else {
    card.classList.add("is-image-missing");
    media.append(fallback);
  }

  const caption = createElement("span", "album-card__body");
  const eyebrow = createElement("span", "album-card__category", category);
  const heading = createElement("span", "album-card__title", title);
  const meta = createElement("span", "album-card__meta");
  const count = createElement("span", "album-card__count", photoCount);
  meta.append(count);

  const dateText = formatDate(album.event_date);
  if (dateText) meta.append(createElement("span", "album-card__date", dateText));

  const descriptionText = normalizeText(album.description);
  const description = createElement("span", "album-card__description", descriptionText);
  if (!descriptionText) description.hidden = true;

  const cta = createElement("span", "album-card__cta", "Lihat Album");
  caption.append(eyebrow, heading, meta, description, cta);
  card.append(media, caption);

  return card;
}

function normalizePhoto(photo, index) {
  const imagePath = safeRelativePath(photo?.image_path);
  const caption = normalizeText(photo?.caption);
  const alt = normalizeText(photo?.alt_text, `Foto album ${index + 1}`);
  return { imagePath, caption, alt };
}

function captionForDisplay(caption) {
  const text = normalizeText(caption);
  if (!text) return "";
  return text.localeCompare(albumState.title, "id", { sensitivity: "base" }) === 0 ? "" : text;
}

function prefersReducedMotion() {
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function albumPageCount() {
  return Math.max(1, Math.ceil(albumState.photos.length / ALBUM_PHOTOS_PER_PAGE));
}

function focusElement(element) {
  if (!element) return;
  element.focus({ preventScroll: true });
}

function navbarOffset() {
  const navbar = document.getElementById("navbar");
  if (!navbar) return 0;
  const rect = navbar.getBoundingClientRect();
  return rect.height > 0 ? rect.height : 0;
}

function scrollToGalleryContent(elements) {
  const target = state.view === "detail"
    ? elements.detail
    : elements.filterGroup || elements.grid || elements.section;
  if (!target) return;

  const top = target.getBoundingClientRect().top + window.scrollY - navbarOffset() - 20;
  const current = window.scrollY;
  const viewportBottom = current + window.innerHeight;
  const isComfortablyVisible = top >= current - 12 && top <= viewportBottom - Math.min(window.innerHeight * 0.35, 240);

  if (isComfortablyVisible) return;
  window.scrollTo({
    top: Math.max(0, top),
    behavior: prefersReducedMotion() ? "auto" : "smooth"
  });
}

function showListView(elements, restoreScroll = true) {
  state.view = "list";
  setHidden(elements.filterGroup, false);
  setHidden(elements.grid, false);
  setHidden(elements.pagination, elements.pagination.children.length === 0);
  setHidden(elements.empty, elements.empty.textContent === "" || elements.grid.children.length > 0);
  setHidden(elements.detail, true);
  elements.section.classList.remove("is-album-detail-view");

  if (albumState.controller) {
    albumState.controller.abort();
    albumState.controller = null;
  }

  if (restoreScroll) {
    window.scrollTo({ top: state.listScrollY, behavior: prefersReducedMotion() ? "auto" : "smooth" });
  }

  if (state.lastAlbumCard?.isConnected) {
    focusElement(state.lastAlbumCard);
  }
}

function showDetailShell(elements, opener) {
  state.view = "detail";
  state.listScrollY = window.scrollY;
  state.lastAlbumCard = opener || null;
  setHidden(elements.filterGroup, true);
  setHidden(elements.loading, true);
  setHidden(elements.grid, true);
  setHidden(elements.pagination, true);
  setHidden(elements.empty, true);
  setHidden(elements.detail, false);
  setHidden(elements.detailLoading, false);
  elements.detailStatus.classList.remove("is-error");
  setHidden(elements.detailStatus, true);
  setHidden(elements.detailGrid, true);
  setHidden(elements.detailPagination, true);
  elements.detailGrid.replaceChildren();
  elements.detailPagination.replaceChildren();
  elements.detailTitle.textContent = "Album Galeri";
  elements.detailMeta.textContent = "";
  elements.section.classList.add("is-album-detail-view");
  scrollToGalleryContent(elements);
  focusElement(elements.detailBack);
}

function createDetailPageButton(label, page, options = {}) {
  const button = document.createElement("button");
  button.type = "button";
  button.className = "gallery-album-detail__page-button";
  button.textContent = label;
  button.dataset.albumPhotoPage = String(page);
  button.disabled = Boolean(options.disabled);
  if (options.current) {
    button.classList.add("is-active");
    button.setAttribute("aria-current", "page");
  }
  if (options.label) button.setAttribute("aria-label", options.label);
  return button;
}

function renderDetailPagination(elements) {
  const totalPages = albumPageCount();
  elements.detailPagination.replaceChildren();
  if (totalPages <= 1) {
    setHidden(elements.detailPagination, true);
    return;
  }

  albumState.page = Math.min(Math.max(albumState.page, 1), totalPages);
  const fragment = document.createDocumentFragment();
  fragment.append(createDetailPageButton("‹", Math.max(1, albumState.page - 1), {
    disabled: albumState.page <= 1,
    label: "Halaman foto sebelumnya"
  }));

  for (let page = 1; page <= totalPages; page += 1) {
    fragment.append(createDetailPageButton(String(page), page, {
      current: page === albumState.page,
      label: `Halaman foto ${page}`
    }));
  }

  fragment.append(createDetailPageButton("›", Math.min(totalPages, albumState.page + 1), {
    disabled: albumState.page >= totalPages,
    label: "Halaman foto berikutnya"
  }));

  elements.detailPagination.append(fragment);
  setHidden(elements.detailPagination, false);
}

function createPhotoCard(photo, index, total) {
  const button = createElement("button", "gallery-album-detail__photo-card");
  button.type = "button";
  button.dataset.photoIndex = String(index);
  button.setAttribute("aria-label", `Buka foto ${index + 1} dari ${total}`);

  const image = document.createElement("img");
  image.src = photo.imagePath;
  image.alt = "";
  image.loading = "lazy";
  image.decoding = "async";
  image.addEventListener("error", () => {
    image.hidden = true;
    button.classList.add("is-image-missing");
  }, { once: true });
  button.append(image);

  const caption = captionForDisplay(photo.caption);
  if (caption) button.append(createElement("span", "gallery-album-detail__photo-caption", caption));

  return button;
}

function renderAlbumDetailGrid(elements) {
  elements.detailGrid.replaceChildren();

  if (!albumState.photos.length) {
    elements.detailStatus.textContent = "Album ini belum memiliki foto.";
    setHidden(elements.detailStatus, false);
    setHidden(elements.detailGrid, true);
    setHidden(elements.detailPagination, true);
    return;
  }

  const total = albumState.photos.length;
  albumState.page = Math.min(Math.max(albumState.page, 1), albumPageCount());
  const start = (albumState.page - 1) * ALBUM_PHOTOS_PER_PAGE;
  const pagePhotos = albumState.photos.slice(start, start + ALBUM_PHOTOS_PER_PAGE);
  const fragment = document.createDocumentFragment();

  pagePhotos.forEach((photo, offset) => fragment.append(createPhotoCard(photo, start + offset, total)));
  elements.detailGrid.append(fragment);
  setHidden(elements.detailStatus, true);
  setHidden(elements.detailGrid, false);
  renderDetailPagination(elements);
}

function renderAlbumDetail(elements, payload) {
  const album = payload.album || {};
  const photos = Array.isArray(payload.photos) ? payload.photos.map(normalizePhoto).filter((photo) => photo.imagePath) : [];
  albumState.title = normalizeText(album.title, "Album Galeri");
  albumState.category = normalizeText(album.category, "Galeri");
  albumState.photos = photos;
  albumState.page = 1;
  albumState.activeIndex = 0;

  elements.detailTitle.textContent = albumState.title;
  elements.detailMeta.textContent = `${albumState.category} ${META_SEPARATOR} ${formatPhotoCount(photos.length || album.photo_count || 0)}`;
  setHidden(elements.detailLoading, true);
  renderAlbumDetailGrid(elements);
}

async function openAlbum(card, elements) {
  const slug = normalizeText(card.dataset.slug);
  if (!slug) return;

  showDetailShell(elements, card);
  const token = albumState.token + 1;
  albumState.token = token;

  if (albumState.controller) albumState.controller.abort();
  const controller = new AbortController();
  albumState.controller = controller;

  try {
    const response = await fetch(buildAlbumDetailUrl(slug), {
      method: "GET",
      headers: { Accept: "application/json" },
      signal: controller.signal
    });

    if (!response.ok) throw new Error("Album detail API failed");

    const payload = await response.json();
    if (albumState.token !== token || state.view !== "detail") return;
    if (!payload || payload.success !== true || !payload.album) {
      throw new Error("Album detail API returned invalid payload");
    }

    renderAlbumDetail(elements, payload);
  } catch (error) {
    if (error.name === "AbortError") return;
    if (albumState.token === token && state.view === "detail") {
      setHidden(elements.detailLoading, true);
      elements.detailStatus.textContent = "Album belum dapat dimuat. Silakan coba lagi.";
      elements.detailStatus.classList.add("is-error");
      setHidden(elements.detailStatus, false);
      setHidden(elements.detailGrid, true);
      setHidden(elements.detailPagination, true);
    }
  } finally {
    if (albumState.controller === controller) albumState.controller = null;
  }
}

function createLightbox() {
  const root = createElement("div", "gallery-lightbox");
  root.hidden = true;
  root.setAttribute("role", "dialog");
  root.setAttribute("aria-modal", "true");
  root.setAttribute("aria-label", "Pratinjau foto album");

  const backdrop = createElement("button", "gallery-lightbox__backdrop");
  backdrop.type = "button";
  backdrop.setAttribute("aria-label", "Tutup foto");

  const image = document.createElement("img");
  image.className = "gallery-lightbox__image";
  image.decoding = "async";

  const closeButton = createElement("button", "gallery-lightbox__button gallery-lightbox__close", "×");
  closeButton.type = "button";
  closeButton.setAttribute("aria-label", "Tutup foto");

  const prevButton = createElement("button", "gallery-lightbox__button gallery-lightbox__prev", "‹");
  prevButton.type = "button";
  prevButton.setAttribute("aria-label", "Foto sebelumnya");

  const nextButton = createElement("button", "gallery-lightbox__button gallery-lightbox__next", "›");
  nextButton.type = "button";
  nextButton.setAttribute("aria-label", "Foto berikutnya");

  const caption = createElement("p", "gallery-lightbox__caption");
  const counter = createElement("p", "gallery-lightbox__count");
  const figure = createElement("figure", "gallery-lightbox__figure");
  figure.append(image, caption, counter);
  root.append(backdrop, figure, closeButton, prevButton, nextButton);
  document.body.append(root);

  return { root, backdrop, figure, image, closeButton, prevButton, nextButton, caption, counter };
}

function getLightboxElements() {
  if (!lightboxState.elements) {
    lightboxState.elements = createLightbox();
    bindLightboxEvents(lightboxState.elements);
  }
  return lightboxState.elements;
}

function lockLightboxScroll() {
  lightboxState.scrollY = window.scrollY;
  document.documentElement.classList.add("gallery-lightbox-open");
  document.body.classList.add("gallery-lightbox-open");
}

function unlockLightboxScroll() {
  document.documentElement.classList.remove("gallery-lightbox-open");
  document.body.classList.remove("gallery-lightbox-open");
}

function renderLightboxPhoto() {
  const elements = getLightboxElements();
  const photo = albumState.photos[albumState.activeIndex];
  const total = albumState.photos.length;
  if (!photo) return;

  elements.image.classList.remove("is-switching");
  elements.image.src = photo.imagePath;
  elements.image.alt = photo.alt;
  if (!prefersReducedMotion()) {
    void elements.image.offsetWidth;
    elements.image.classList.add("is-switching");
  }

  const caption = captionForDisplay(photo.caption);
  elements.caption.textContent = caption;
  elements.caption.hidden = !caption;
  elements.counter.textContent = `${albumState.activeIndex + 1} / ${total}`;

  const single = total <= 1;
  elements.prevButton.hidden = single;
  elements.nextButton.hidden = single;
  elements.prevButton.disabled = single;
  elements.nextButton.disabled = single;
}

function openLightbox(index, opener) {
  const total = albumState.photos.length;
  if (!total) return;

  const elements = getLightboxElements();
  albumState.activeIndex = Math.min(Math.max(index, 0), total - 1);
  lightboxState.opener = opener;
  lightboxState.isOpen = true;
  renderLightboxPhoto();
  elements.root.hidden = false;
  lockLightboxScroll();
  requestAnimationFrame(() => elements.closeButton.focus({ preventScroll: true }));
}

function closeLightbox() {
  if (!lightboxState.isOpen) return;
  const elements = getLightboxElements();
  elements.root.hidden = true;
  elements.image.removeAttribute("src");
  lightboxState.isOpen = false;
  unlockLightboxScroll();

  const opener = lightboxState.opener;
  lightboxState.opener = null;
  if (opener && typeof opener.focus === "function") {
    opener.focus({ preventScroll: true });
  }
}

function moveLightboxPhoto(direction) {
  if (!lightboxState.isOpen || albumState.photos.length <= 1) return;
  const total = albumState.photos.length;
  albumState.activeIndex = (albumState.activeIndex + direction + total) % total;
  renderLightboxPhoto();
}

function focusableElements(root) {
  return Array.from(root.querySelectorAll("a[href], button:not(:disabled), [tabindex]:not([tabindex='-1'])"))
    .filter((element) => !element.hidden && element.offsetParent !== null);
}

function bindLightboxEvents(elements) {
  elements.backdrop.addEventListener("click", closeLightbox);
  elements.closeButton.addEventListener("click", closeLightbox);
  elements.prevButton.addEventListener("click", () => moveLightboxPhoto(-1));
  elements.nextButton.addEventListener("click", () => moveLightboxPhoto(1));

  elements.root.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      event.preventDefault();
      closeLightbox();
      return;
    }
    if (event.key === "ArrowLeft") {
      event.preventDefault();
      moveLightboxPhoto(-1);
      return;
    }
    if (event.key === "ArrowRight") {
      event.preventDefault();
      moveLightboxPhoto(1);
      return;
    }
    if (event.key === "Tab") {
      const focusable = focusableElements(elements.root);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus({ preventScroll: true });
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus({ preventScroll: true });
      }
    }
  });
}

function renderAlbums(elements, albums, pagination) {
  elements.grid.replaceChildren();

  if (!albums.length) {
    const message = pagination?.total === 0 && state.category === "semua"
      ? "Dokumentasi galeri belum tersedia."
      : "Belum ada album pada kategori ini.";
    showMessage(elements, message);
    return;
  }

  setHidden(elements.empty, true);
  const fragment = document.createDocumentFragment();
  albums.forEach((album) => fragment.append(createAlbumCard(album)));
  elements.grid.append(fragment);
}

function paginationRange(currentPage, totalPages) {
  const pages = new Set([1, totalPages, currentPage]);
  for (let page = currentPage - 1; page <= currentPage + 1; page += 1) {
    if (page >= 1 && page <= totalPages) pages.add(page);
  }
  return [...pages].sort((a, b) => a - b);
}

function createPageButton(label, page, options = {}) {
  const button = document.createElement("button");
  button.type = "button";
  button.className = "gallery-pagination__button";
  button.textContent = label;
  button.dataset.page = String(page);
  button.disabled = Boolean(options.disabled);
  if (options.current) {
    button.classList.add("is-active");
    button.setAttribute("aria-current", "page");
  }
  if (options.label) button.setAttribute("aria-label", options.label);
  return button;
}

function renderPagination(elements, pagination) {
  const totalPages = Number.parseInt(pagination?.total_pages ?? 1, 10);
  const currentPage = Number.parseInt(pagination?.page ?? state.page, 10);

  elements.pagination.replaceChildren();
  if (!Number.isFinite(totalPages) || totalPages <= 1) {
    setHidden(elements.pagination, true);
    return;
  }

  const fragment = document.createDocumentFragment();
  fragment.append(createPageButton("Sebelumnya", Math.max(1, currentPage - 1), {
    disabled: !pagination.has_previous,
    label: "Halaman album sebelumnya"
  }));

  let previousPage = 0;
  paginationRange(currentPage, totalPages).forEach((page) => {
    if (previousPage && page - previousPage > 1) {
      fragment.append(createElement("span", "gallery-pagination__ellipsis", "..."));
    }
    fragment.append(createPageButton(String(page), page, {
      current: page === currentPage,
      label: `Halaman ${page}`
    }));
    previousPage = page;
  });

  fragment.append(createPageButton("Berikutnya", Math.min(totalPages, currentPage + 1), {
    disabled: !pagination.has_next,
    label: "Halaman album berikutnya"
  }));

  elements.pagination.append(fragment);
  setHidden(elements.pagination, false);
}

async function loadAlbums(elements) {
  if (state.controller) state.controller.abort();

  const controller = new AbortController();
  state.controller = controller;
  setLoading(elements, true);
  setHidden(elements.empty, true);
  setHidden(elements.pagination, true);

  try {
    const response = await fetch(buildApiUrl(), {
      method: "GET",
      headers: { Accept: "application/json" },
      signal: controller.signal
    });

    if (!response.ok) throw new Error("Gallery album API failed");

    const payload = await response.json();
    if (!payload || payload.success !== true || !Array.isArray(payload.data)) {
      throw new Error("Gallery album API returned invalid payload");
    }

    renderAlbums(elements, payload.data, payload.pagination);
    renderPagination(elements, payload.pagination || {});
  } catch (error) {
    if (error.name === "AbortError") return;
    elements.grid.replaceChildren();
    showMessage(elements, "Galeri belum dapat dimuat. Silakan coba lagi.", true);
  } finally {
    if (state.controller === controller) setLoading(elements, false);
  }
}

function collectElements(section) {
  return {
    section,
    filterGroup: section.querySelector(selectors.filters),
    grid: section.querySelector(selectors.grid),
    loading: section.querySelector(selectors.loading),
    empty: section.querySelector(selectors.empty),
    pagination: section.querySelector(selectors.pagination),
    detail: section.querySelector(selectors.detail),
    detailBack: section.querySelector(selectors.detailBack),
    detailTitle: section.querySelector(selectors.detailTitle),
    detailMeta: section.querySelector(selectors.detailMeta),
    detailLoading: section.querySelector(selectors.detailLoading),
    detailStatus: section.querySelector(selectors.detailStatus),
    detailGrid: section.querySelector(selectors.detailGrid),
    detailPagination: section.querySelector(selectors.detailPagination)
  };
}

function initGalleryAlbumsPage() {
  const section = document.querySelector(selectors.section);
  if (!section) return;

  const elements = collectElements(section);
  if (
    !elements.filterGroup ||
    !elements.grid ||
    !elements.pagination ||
    !elements.detail ||
    !elements.detailBack ||
    !elements.detailGrid ||
    !elements.detailPagination
  ) {
    return;
  }

  syncFilterButtons(elements.filterGroup);

  elements.filterGroup.addEventListener("click", (event) => {
    const button = event.target.closest("[data-filter]");
    if (!button || !elements.filterGroup.contains(button)) return;

    const nextCategory = button.dataset.filter || "semua";
    if (!Object.prototype.hasOwnProperty.call(FILTER_CATEGORIES, nextCategory)) return;

    state.category = nextCategory;
    state.page = 1;
    syncFilterButtons(elements.filterGroup);
    loadAlbums(elements);
  });

  elements.grid.addEventListener("click", (event) => {
    const card = event.target.closest("[data-album-card]");
    if (!card || !elements.grid.contains(card)) return;
    event.preventDefault();
    openAlbum(card, elements);
  });

  elements.pagination.addEventListener("click", (event) => {
    const button = event.target.closest("[data-page]");
    if (!button || button.disabled) return;

    const nextPage = Number.parseInt(button.dataset.page, 10);
    if (!Number.isFinite(nextPage) || nextPage === state.page) return;

    state.page = nextPage;
    loadAlbums(elements);
    section.scrollIntoView({ block: "start", behavior: prefersReducedMotion() ? "auto" : "smooth" });
  });

  elements.detailBack.addEventListener("click", () => showListView(elements));

  elements.detailGrid.addEventListener("click", (event) => {
    const button = event.target.closest("[data-photo-index]");
    if (!button || !elements.detailGrid.contains(button)) return;
    const index = Number.parseInt(button.dataset.photoIndex, 10);
    if (!Number.isFinite(index)) return;
    openLightbox(index, button);
  });

  elements.detailPagination.addEventListener("click", (event) => {
    const button = event.target.closest("[data-album-photo-page]");
    if (!button || button.disabled || !elements.detailPagination.contains(button)) return;
    const page = Number.parseInt(button.dataset.albumPhotoPage, 10);
    if (!Number.isFinite(page) || page === albumState.page) return;
    albumState.page = page;
    renderAlbumDetailGrid(elements);
    scrollToGalleryContent(elements);
  });

  loadAlbums(elements);
}

document.addEventListener("DOMContentLoaded", initGalleryAlbumsPage);