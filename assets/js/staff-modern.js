const STAFF_SECTION_SELECTOR = "[data-school-staff-section]";
const STAFF_API_LIMIT = 100;
const FALLBACK_PHOTO_PATH = "assets/img/logo.png";
const DANGEROUS_SCHEME_PATTERN = /^(?:javascript|vbscript|data):/i;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const SWIPE_THRESHOLD = 48;
const SWAP_DURATION_MS = 520;
const MODAL_CLOSE_DELAY_MS = 180;

const CATEGORY_LABELS = {
  all: "Semua",
  pimpinan: "Tim Kepemimpinan",
  guru: "Guru Pengajar",
  staf: "Staff dan Karyawan"
};

const POSITION_CATEGORY_MAP = new Map([
  ["kepala sekolah", "pimpinan"],
  ["wakil kepala sekolah", "pimpinan"],
  ["koordinator sekolah", "pimpinan"],
  ["guru kelas", "guru"],
  ["guru agama katholik", "guru"],
  ["guru agama katolik", "guru"],
  ["guru tik & bahasa inggris", "guru"],
  ["guru mata pelajaran", "guru"],
  ["wali kelas", "guru"],
  ["staff tata usaha", "staf"],
  ["staf tata usaha", "staf"],
  ["tata usaha", "staf"],
  ["operator sekolah", "staf"],
  ["pustakawan", "staf"],
  ["karyawan", "staf"],
  ["petugas keamanan", "staf"],
  ["satpam", "staf"],
  ["petugas kebersihan", "staf"]
]);

const warnedPositionKeys = new Set();

function getBasePath() {
  const base = document.querySelector("base")?.getAttribute("href");
  if (base) {
    return new URL(base, window.location.href).pathname.replace(/\/$/, "");
  }

  try {
    const modulePath = new URL(import.meta.url).pathname.replace(/\\/g, "/");
    const assetMarker = "/assets/js/";
    const markerIndex = modulePath.lastIndexOf(assetMarker);
    if (markerIndex >= 0) {
      const basePath = modulePath.slice(0, markerIndex);
      return basePath === "/" ? "" : basePath;
    }
  } catch (error) {
    console.warn("[school-staff] Base path detection fell back to location path.");
  }

  const path = window.location.pathname.replace(/\\/g, "/");
  const lastSlash = path.lastIndexOf("/");
  return lastSlash > 0 ? path.slice(0, lastSlash) : "";
}

function localPath(path) {
  const basePath = getBasePath();
  return `${basePath}/${String(path).replace(/^\/+/, "")}`;
}

function cleanText(value) {
  return String(value ?? "").replace(/\s+/g, " ").trim();
}

function normalizePosition(value) {
  return cleanText(value).toLocaleLowerCase("id-ID");
}

function mapPositionToCategory(position) {
  const key = normalizePosition(position);
  if (!key) {
    return "";
  }

  const category = POSITION_CATEGORY_MAP.get(key) || "";
  if (!category && !warnedPositionKeys.has(key)) {
    warnedPositionKeys.add(key);
    console.warn("[school-staff] Unmapped staff position; item remains visible only in Semua.");
  }

  return category;
}

function isValidEmail(value) {
  return EMAIL_PATTERN.test(cleanText(value));
}

function fallbackPhotoUrl() {
  return new URL(localPath(FALLBACK_PHOTO_PATH), window.location.href).href;
}

function resolvePhoto(value) {
  const fallback = fallbackPhotoUrl();
  const rawValue = String(value ?? "").trim();
  if (!rawValue || DANGEROUS_SCHEME_PATTERN.test(rawValue)) {
    return fallback;
  }

  if (rawValue.startsWith("//")) {
    try {
      const protocolRelativeUrl = new URL(rawValue, window.location.protocol);
      return protocolRelativeUrl.protocol === "https:" ? protocolRelativeUrl.href : fallback;
    } catch (error) {
      return fallback;
    }
  }

  if (/^[a-z][a-z0-9+.-]*:/i.test(rawValue)) {
    try {
      const url = new URL(rawValue);
      if (url.protocol === "https:") {
        return url.href;
      }

      if (url.protocol === "http:" && url.origin === window.location.origin) {
        return url.href;
      }
    } catch (error) {
      return fallback;
    }

    return fallback;
  }

  let cleanPath = rawValue.replace(/\\/g, "/").replace(/^\/+/, "");
  const baseSegment = getBasePath().replace(/^\/+/, "");
  if (baseSegment && cleanPath.startsWith(`${baseSegment}/`)) {
    cleanPath = cleanPath.slice(baseSegment.length + 1);
  }

  if (/^(?:uploads|assets)\//i.test(cleanPath) || cleanPath.includes("/")) {
    return new URL(localPath(cleanPath), window.location.href).href;
  }

  return new URL(localPath(`uploads/staff/${encodeURIComponent(cleanPath)}`), window.location.href).href;
}

function createSvgIcon(name) {
  const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  svg.setAttribute("viewBox", "0 0 24 24");
  svg.setAttribute("aria-hidden", "true");
  svg.setAttribute("focusable", "false");

  const paths = {
    chevronLeft: ["m15 18-6-6 6-6"],
    chevronRight: ["m9 18 6-6-6-6"],
    search: ["M21 21l-4.35-4.35", "M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14Z"],
    mail: [
      "M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z",
      "m22 7-10 6L2 7"
    ],
    close: ["M6 6l12 12", "M18 6 6 18"]
  };

  (paths[name] || []).forEach((definition) => {
    const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
    path.setAttribute("d", definition);
    svg.append(path);
  });

  return svg;
}

function setImageSource(image, source, altText) {
  image.dataset.schoolStaffFallbackApplied = "false";
  image.src = source || fallbackPhotoUrl();
  image.alt = altText;
}

function handleImageError(event) {
  const image = event.currentTarget;
  if (!(image instanceof HTMLImageElement) || image.dataset.schoolStaffFallbackApplied === "true") {
    return;
  }

  image.dataset.schoolStaffFallbackApplied = "true";
  image.src = fallbackPhotoUrl();
}

function normalizeStaffItem(item, index) {
  const role = cleanText(item?.jabatan) || "Tenaga Pendidik dan Kependidikan";
  const email = cleanText(item?.email);

  return {
    id: item?.id ?? `staff-${index + 1}`,
    name: cleanText(item?.nama) || "Profil Guru dan Staf",
    role,
    motto: cleanText(item?.deskripsi),
    email: isValidEmail(email) ? email : "",
    photo: resolvePhoto(item?.foto),
    order: Number.isFinite(Number(item?.urutan)) ? Number(item.urutan) : index,
    category: mapPositionToCategory(role)
  };
}

async function fetchStaff(limit = STAFF_API_LIMIT) {
  const url = new URL(localPath("api/public-staff.php"), window.location.href);
  url.searchParams.set("limit", String(limit));

  const response = await fetch(url, {
    headers: { Accept: "application/json" },
    credentials: "same-origin"
  });

  if (!response.ok) {
    throw new Error("Staff API request failed.");
  }

  let payload;
  try {
    payload = await response.json();
  } catch (error) {
    throw new Error("Staff API returned invalid JSON.");
  }

  if (payload?.status !== "success" || !Array.isArray(payload.data)) {
    throw new Error("Staff API returned an unexpected payload.");
  }

  return payload.data.map(normalizeStaffItem);
}

function getVisibleStaff(state) {
  if (state.activeFilter === "all") {
    return state.staffItems;
  }

  return state.staffItems.filter((item) => item.category === state.activeFilter);
}

function getFilterLabel(filter) {
  return CATEGORY_LABELS[filter] || CATEGORY_LABELS.all;
}

function isReducedMotion() {
  return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function clearElement(element) {
  element.replaceChildren();
}

function createEmptyState(message, retryHandler = null) {
  const wrapper = document.createElement("div");
  wrapper.className = "school-staff-empty-state";

  const text = document.createElement("p");
  text.textContent = message;
  wrapper.append(text);

  if (retryHandler) {
    const button = document.createElement("button");
    button.className = "school-staff-empty-state__button";
    button.type = "button";
    button.textContent = "Coba lagi";
    button.addEventListener("click", retryHandler);
    wrapper.append(button);
  }

  return wrapper;
}

function createStaffCard(state, staff) {
  const card = document.createElement("article");
  card.className = "school-staff-card";
  card.dataset.schoolStaffCategory = staff.category || "";

  const photoShell = document.createElement("div");
  photoShell.className = "school-staff-card__photo-shell";

  const photoButton = document.createElement("button");
  photoButton.className = "school-staff-card__photo-button";
  photoButton.type = "button";
  photoButton.setAttribute("aria-label", `Lihat foto lengkap ${staff.name}`);

  const photoFrame = document.createElement("span");
  photoFrame.className = "school-staff-card__photo-frame";
  photoFrame.setAttribute("aria-hidden", "true");

  const photo = document.createElement("img");
  photo.loading = "lazy";
  photo.decoding = "async";
  photo.addEventListener("error", handleImageError);
  setImageSource(photo, staff.photo, "");

  const overlay = document.createElement("span");
  overlay.className = "school-staff-card__photo-overlay";
  overlay.append(createSvgIcon("search"));

  photoFrame.append(photo, overlay);
  photoButton.append(photoFrame);
  photoButton.addEventListener("click", () => openModal(state, staff, photoButton));
  photoShell.append(photoButton);

  const body = document.createElement("div");
  body.className = "school-staff-card__body";

  const name = document.createElement("h3");
  name.className = "school-staff-card__name";
  name.textContent = staff.name;

  const role = document.createElement("span");
  role.className = "school-staff-card__role";
  role.textContent = staff.role;

  body.append(name, role);

  if (staff.motto) {
    const motto = document.createElement("p");
    motto.className = "school-staff-card__motto";
    motto.textContent = staff.motto;
    body.append(motto);
  }

  if (staff.email) {
    const email = document.createElement("a");
    email.className = "school-staff-card__email";
    email.href = `mailto:${staff.email}`;
    email.setAttribute("aria-label", `Kirim email kepada ${staff.name}`);
    email.append(createSvgIcon("mail"));

    const emailText = document.createElement("span");
    emailText.textContent = staff.email;
    email.append(emailText);
    body.append(email);
  }

  card.append(photoShell, body);
  return card;
}

function updateSliderControls(state) {
  const maxScrollLeft = Math.max(0, state.slider.scrollWidth - state.slider.clientWidth);
  const canScroll = maxScrollLeft > 2;

  state.previousButton.disabled = !canScroll || state.slider.scrollLeft <= 2;
  state.nextButton.disabled = !canScroll || state.slider.scrollLeft >= maxScrollLeft - 2;
}

function scrollSlider(state, direction) {
  const firstCard = state.track.querySelector(".school-staff-card");
  const gap = Number.parseFloat(getComputedStyle(state.track).columnGap) || 22;
  const cardWidth = firstCard ? firstCard.getBoundingClientRect().width : 280;
  const visibleCount = Math.max(1, Math.floor(state.slider.clientWidth / (cardWidth + gap)));
  const distance = (cardWidth + gap) * Math.min(visibleCount, 3);

  state.slider.scrollBy({
    left: direction * distance,
    behavior: isReducedMotion() ? "auto" : "smooth"
  });
}

function renderStaff(state) {
  const visibleStaff = getVisibleStaff(state);
  clearElement(state.track);

  if (!state.staffItems.length) {
    state.track.append(createEmptyState("Data Guru dan Staf belum tersedia."));
  } else if (!visibleStaff.length) {
    state.track.append(createEmptyState("Belum ada data pada kategori ini."));
  } else {
    const fragment = document.createDocumentFragment();
    visibleStaff.forEach((staff) => {
      fragment.append(createStaffCard(state, staff));
    });
    state.track.append(fragment);
  }

  state.slider.scrollTo({ left: 0, behavior: "auto" });
  state.status.textContent = `${visibleStaff.length} profil ditampilkan untuk ${getFilterLabel(state.activeFilter)}.`;
  window.requestAnimationFrame(() => updateSliderControls(state));
}

function setLoadingState(state) {
  clearElement(state.track);
  state.track.append(createEmptyState("Memuat data Guru dan Staf..."));
  state.status.textContent = "Memuat data Guru dan Staf.";
  updateSliderControls(state);
}

function setErrorState(state) {
  clearElement(state.track);
  state.track.append(
    createEmptyState(
      "Data Guru dan Staf belum dapat dimuat. Silakan coba kembali.",
      () => loadStaff(state)
    )
  );
  state.status.textContent = "Data Guru dan Staf belum dapat dimuat.";
  updateSliderControls(state);
}

async function loadStaff(state) {
  setLoadingState(state);

  try {
    const limit = Number.parseInt(state.section.dataset.schoolStaffLimit || "", 10) || STAFF_API_LIMIT;
    state.staffItems = await fetchStaff(Math.min(Math.max(limit, 1), STAFF_API_LIMIT));
    renderStaff(state);
  } catch (error) {
    console.error("[school-staff] Unable to load staff data.");
    setErrorState(state);
  }
}

function selectFilter(state, button) {
  const nextFilter = button.dataset.schoolStaffFilter || "all";
  if (state.activeFilter === nextFilter) {
    return;
  }

  closeModal(state, { restoreFocus: false });
  state.activeFilter = nextFilter;

  state.filterButtons.forEach((item) => {
    const isActive = item === button;
    item.classList.toggle("is-active", isActive);
    item.setAttribute("aria-pressed", String(isActive));
  });

  renderStaff(state);
}

function configureModalImage(image) {
  image.decoding = "async";
  image.addEventListener("error", handleImageError);
}

function updatePreviewButton(button, image, staff, label, hidden) {
  button.hidden = hidden;
  if (hidden || !staff) {
    image.removeAttribute("src");
    image.alt = "";
    return;
  }

  setImageSource(image, staff.photo, "");
  button.setAttribute("aria-label", `${label}: ${staff.name}`);
}

function updateModalPreviews(state) {
  const total = state.modalStaffList.length;
  if (total < 2) {
    updatePreviewButton(state.previousPreviewButton, state.previousPreviewImage, null, "", true);
    updatePreviewButton(state.nextPreviewButton, state.nextPreviewImage, null, "", true);
    return;
  }

  if (total === 2) {
    const nextStaff = state.modalStaffList[(state.modalIndex + 1) % total];
    updatePreviewButton(state.previousPreviewButton, state.previousPreviewImage, null, "", true);
    updatePreviewButton(state.nextPreviewButton, state.nextPreviewImage, nextStaff, "Tampilkan foto berikutnya", false);
    return;
  }

  const previousStaff = state.modalStaffList[(state.modalIndex - 1 + total) % total];
  const nextStaff = state.modalStaffList[(state.modalIndex + 1) % total];
  updatePreviewButton(state.previousPreviewButton, state.previousPreviewImage, previousStaff, "Tampilkan foto sebelumnya", false);
  updatePreviewButton(state.nextPreviewButton, state.nextPreviewImage, nextStaff, "Tampilkan foto berikutnya", false);
}

function updateModalPhoto(state, staff, announce = false) {
  setImageSource(state.modalPhoto, staff.photo, `Foto lengkap ${staff.name}`);
  updateModalPreviews(state);

  if (announce) {
    state.modalAnnouncement.textContent = `Foto ${state.modalIndex + 1} dari ${state.modalStaffList.length}: ${staff.name}`;
  }
}

function openModal(state, staff, trigger) {
  window.clearTimeout(state.closeTimer);
  state.modalTrigger = trigger;
  state.modalStaffList = getVisibleStaff(state);
  state.modalIndex = state.modalStaffList.findIndex((item) => String(item.id) === String(staff.id));

  if (state.modalIndex < 0) {
    return;
  }

  updateModalPhoto(state, state.modalStaffList[state.modalIndex]);
  state.modal.hidden = false;
  document.body.classList.add("school-staff-modal-open");

  window.requestAnimationFrame(() => {
    state.modal.classList.add("is-open");
    state.closeButton.focus({ preventScroll: true });
  });
}

function cleanupActiveSwap(state) {
  if (state.activeSwapCleanup) {
    state.activeSwapCleanup();
  }
}

function createSwapGhost(state, staff, rectangle, variant) {
  const ghost = state.modalCard.cloneNode(true);
  ghost.querySelectorAll("button, .school-staff-sr-only").forEach((element) => element.remove());
  ghost.querySelectorAll("[id]").forEach((element) => element.removeAttribute("id"));
  ghost.querySelectorAll("[data-school-staff-modal-photo]").forEach((image) => {
    if (image instanceof HTMLImageElement) {
      setImageSource(image, staff.photo, "");
    }
  });

  ghost.classList.add("school-staff-modal__ghost", `school-staff-modal__ghost--${variant}`);
  ghost.setAttribute("aria-hidden", "true");
  Object.assign(ghost.style, {
    position: "fixed",
    zIndex: variant === "incoming" ? "2102" : "2101",
    top: `${rectangle.top}px`,
    left: `${rectangle.left}px`,
    width: `${rectangle.width}px`,
    height: `${rectangle.height}px`,
    maxHeight: "none",
    margin: "0",
    opacity: variant === "incoming" ? "0.68" : "1",
    pointerEvents: "none",
    transform: "none",
    transition: "none"
  });

  state.modal.append(ghost);
  return ghost;
}

function visiblePreviewForDirection(state, direction) {
  if (direction < 0 && !state.previousPreviewButton.hidden) {
    return state.previousPreviewButton;
  }

  return state.nextPreviewButton.hidden ? state.previousPreviewButton : state.nextPreviewButton;
}

async function animateCardSwap(state, direction, nextStaff, commitPhoto) {
  if (isReducedMotion() || typeof state.modalCard.animate !== "function") {
    commitPhoto();
    return;
  }

  const incomingSource = visiblePreviewForDirection(state, direction);
  const outgoingTarget = direction > 0 && !state.previousPreviewButton.hidden
    ? state.previousPreviewButton
    : state.nextPreviewButton;

  const mainRectangle = state.modalCard.getBoundingClientRect();
  const incomingRectangle = incomingSource.getBoundingClientRect();
  const outgoingRectangle = outgoingTarget.getBoundingClientRect();
  const currentStaff = state.modalStaffList[state.modalIndex];
  const incomingGhost = createSwapGhost(state, nextStaff, incomingRectangle, "incoming");
  const outgoingGhost = createSwapGhost(state, currentStaff, mainRectangle, "outgoing");
  const hiddenElements = [state.modalCard, state.previousPreviewButton, state.nextPreviewButton];

  hiddenElements.forEach((element) => {
    element.style.visibility = "hidden";
  });

  const cleanup = () => {
    incomingGhost.remove();
    outgoingGhost.remove();
    hiddenElements.forEach((element) => {
      element.style.visibility = "";
    });
    state.activeSwapCleanup = null;
  };
  state.activeSwapCleanup = cleanup;

  const easing = "cubic-bezier(0.16, 1, 0.3, 1)";
  const incomingRotation = direction > 0 ? 2.2 : -2.2;
  const outgoingRotation = direction > 0 ? -2.2 : 2.2;

  try {
    const incomingAnimation = incomingGhost.animate(
      [
        {
          top: `${incomingRectangle.top}px`,
          left: `${incomingRectangle.left}px`,
          width: `${incomingRectangle.width}px`,
          height: `${incomingRectangle.height}px`,
          opacity: 0.68,
          filter: "saturate(0.82) brightness(0.82)",
          transform: `rotate(${incomingRotation}deg) scale(0.98)`
        },
        {
          top: `${mainRectangle.top}px`,
          left: `${mainRectangle.left}px`,
          width: `${mainRectangle.width}px`,
          height: `${mainRectangle.height}px`,
          opacity: 1,
          filter: "saturate(1) brightness(1)",
          transform: "rotate(0deg) scale(1)"
        }
      ],
      { duration: SWAP_DURATION_MS, easing, fill: "forwards" }
    );

    const outgoingAnimation = outgoingGhost.animate(
      [
        {
          top: `${mainRectangle.top}px`,
          left: `${mainRectangle.left}px`,
          width: `${mainRectangle.width}px`,
          height: `${mainRectangle.height}px`,
          opacity: 1,
          filter: "saturate(1) brightness(1)",
          transform: "rotate(0deg) scale(1)"
        },
        {
          top: `${outgoingRectangle.top}px`,
          left: `${outgoingRectangle.left}px`,
          width: `${outgoingRectangle.width}px`,
          height: `${outgoingRectangle.height}px`,
          opacity: 0.58,
          filter: "saturate(0.8) brightness(0.78)",
          transform: `rotate(${outgoingRotation}deg) scale(0.98)`
        }
      ],
      { duration: SWAP_DURATION_MS, easing, fill: "forwards" }
    );

    await Promise.all([incomingAnimation.finished, outgoingAnimation.finished]);
    commitPhoto();
  } catch (error) {
    commitPhoto();
  } finally {
    cleanup();
  }
}

async function navigateModal(state, direction) {
  if (state.modal.hidden || state.modalIsAnimating || state.modalStaffList.length < 2) {
    return;
  }

  state.modalIsAnimating = true;
  const targetIndex = (state.modalIndex + direction + state.modalStaffList.length) % state.modalStaffList.length;
  const nextStaff = state.modalStaffList[targetIndex];

  const commitPhoto = () => {
    state.modalIndex = targetIndex;
    updateModalPhoto(state, nextStaff, true);
  };

  try {
    await animateCardSwap(state, direction, nextStaff, commitPhoto);
  } finally {
    state.modalIsAnimating = false;
  }
}

function closeModal(state, options = {}) {
  const { restoreFocus = true } = options;
  if (state.modal.hidden) {
    return;
  }

  cleanupActiveSwap(state);
  state.modal.classList.remove("is-open");
  document.body.classList.remove("school-staff-modal-open");
  window.clearTimeout(state.closeTimer);

  state.closeTimer = window.setTimeout(() => {
    state.modal.hidden = true;
    state.modalPhoto.removeAttribute("src");
    state.previousPreviewImage.removeAttribute("src");
    state.nextPreviewImage.removeAttribute("src");
    state.modalIsAnimating = false;

    if (restoreFocus && state.modalTrigger && document.contains(state.modalTrigger)) {
      state.modalTrigger.focus({ preventScroll: true });
    }
  }, isReducedMotion() ? 0 : MODAL_CLOSE_DELAY_MS);
}

function isEditableTarget(target) {
  return target instanceof Element
    && Boolean(target.closest("input, textarea, select, [contenteditable='true']"));
}

function isModalSwipeTarget(target) {
  return target instanceof Element && !target.closest("button, a");
}

function beginModalSwipe(state, clientX, clientY) {
  state.swipeStartX = clientX;
  state.swipeStartY = clientY;
}

function finishModalSwipe(state, clientX, clientY) {
  if (state.swipeStartX === null || state.swipeStartY === null) {
    return;
  }

  const distanceX = clientX - state.swipeStartX;
  const distanceY = clientY - state.swipeStartY;
  state.swipeStartX = null;
  state.swipeStartY = null;

  if (Math.abs(distanceX) >= SWIPE_THRESHOLD && Math.abs(distanceX) > Math.abs(distanceY) * 1.2) {
    navigateModal(state, distanceX < 0 ? 1 : -1);
  }
}

function trapModalFocus(state, event) {
  if (event.key !== "Tab" || state.modal.hidden) {
    return;
  }

  const focusable = Array.from(state.modalDeck.querySelectorAll("button, a[href], [tabindex]:not([tabindex='-1'])"))
    .filter((element) => element instanceof HTMLElement && !element.hidden && !element.disabled);

  if (!focusable.length) {
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

function bindModalEvents(state) {
  state.closeButton.addEventListener("click", () => closeModal(state));
  state.previousPreviewButton.addEventListener("click", () => navigateModal(state, -1));
  state.nextPreviewButton.addEventListener("click", () => navigateModal(state, 1));

  state.modalSlide.addEventListener("pointerdown", (event) => {
    if (event.button !== 0 || !isModalSwipeTarget(event.target)) {
      return;
    }

    state.pointerSwipeActive = true;
    beginModalSwipe(state, event.clientX, event.clientY);
    try {
      state.modalSlide.setPointerCapture(event.pointerId);
    } catch (error) {
      state.pointerSwipeActive = false;
    }
  });

  state.modalSlide.addEventListener("pointerup", (event) => {
    finishModalSwipe(state, event.clientX, event.clientY);
    window.setTimeout(() => {
      state.pointerSwipeActive = false;
    }, 0);
  });

  state.modalSlide.addEventListener("pointercancel", () => {
    state.swipeStartX = null;
    state.swipeStartY = null;
    state.pointerSwipeActive = false;
  });

  state.modalSlide.addEventListener("mousedown", (event) => {
    if (state.pointerSwipeActive || event.button !== 0 || !isModalSwipeTarget(event.target)) {
      return;
    }

    state.mouseSwipeActive = true;
    beginModalSwipe(state, event.clientX, event.clientY);
  });

  state.modalSlide.addEventListener("mouseup", (event) => {
    if (state.swipeStartX === null || state.swipeStartY === null) {
      state.mouseSwipeActive = false;
      return;
    }

    state.mouseSwipeActive = false;
    state.pointerSwipeActive = false;
    finishModalSwipe(state, event.clientX, event.clientY);
  });

  state.modalSlide.addEventListener("touchstart", (event) => {
    if (state.pointerSwipeActive || !isModalSwipeTarget(event.target) || event.touches.length === 0) {
      return;
    }

    const touch = event.touches[0];
    beginModalSwipe(state, touch.clientX, touch.clientY);
  }, { passive: true });

  state.modalSlide.addEventListener("touchend", (event) => {
    if (state.pointerSwipeActive || event.changedTouches.length === 0) {
      return;
    }

    const touch = event.changedTouches[0];
    finishModalSwipe(state, touch.clientX, touch.clientY);
  });

  state.modal.addEventListener("click", (event) => {
    if (event.target === state.modal || event.target === state.modalDeck) {
      closeModal(state);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (state.modal.hidden) {
      return;
    }

    if (event.key === "Escape") {
      event.preventDefault();
      closeModal(state);
      return;
    }

    if (!isEditableTarget(event.target) && event.key === "ArrowLeft") {
      event.preventDefault();
      navigateModal(state, -1);
      return;
    }

    if (!isEditableTarget(event.target) && event.key === "ArrowRight") {
      event.preventDefault();
      navigateModal(state, 1);
      return;
    }

    trapModalFocus(state, event);
  });
}

function createSectionState(section) {
  return {
    section,
    activeFilter: "all",
    staffItems: [],
    modalStaffList: [],
    modalIndex: 0,
    modalIsAnimating: false,
    modalTrigger: null,
    closeTimer: 0,
    swipeStartX: null,
    swipeStartY: null,
    pointerSwipeActive: false,
    mouseSwipeActive: false,
    activeSwapCleanup: null,
    filterButtons: Array.from(section.querySelectorAll("[data-school-staff-filter]")),
    slider: section.querySelector("[data-school-staff-slider]"),
    track: section.querySelector("[data-school-staff-track]"),
    previousButton: section.querySelector("[data-school-staff-slider-prev]"),
    nextButton: section.querySelector("[data-school-staff-slider-next]"),
    status: section.querySelector("[data-school-staff-status]"),
    modal: section.querySelector("[data-school-staff-modal]"),
    modalDeck: section.querySelector("[data-school-staff-modal-deck]"),
    modalCard: section.querySelector("[data-school-staff-modal-card]"),
    modalSlide: section.querySelector("[data-school-staff-modal-slide]"),
    closeButton: section.querySelector("[data-school-staff-modal-close]"),
    modalPhoto: section.querySelector("[data-school-staff-modal-photo]"),
    modalAnnouncement: section.querySelector("[data-school-staff-modal-announcement]"),
    previousPreviewButton: section.querySelector("[data-school-staff-preview='previous']"),
    nextPreviewButton: section.querySelector("[data-school-staff-preview='next']"),
    previousPreviewImage: section.querySelector("[data-school-staff-preview-image='previous']"),
    nextPreviewImage: section.querySelector("[data-school-staff-preview-image='next']")
  };
}

function hasRequiredElements(state) {
  return Boolean(
    state.slider
    && state.track
    && state.previousButton
    && state.nextButton
    && state.status
    && state.modal
    && state.modalDeck
    && state.modalCard
    && state.modalSlide
    && state.closeButton
    && state.modalPhoto
    && state.modalAnnouncement
    && state.previousPreviewButton
    && state.nextPreviewButton
    && state.previousPreviewImage
    && state.nextPreviewImage
  );
}

function initSection(section) {
  if (section.dataset.schoolStaffInitialized === "true") {
    return;
  }

  const state = createSectionState(section);
  if (!hasRequiredElements(state)) {
    console.warn("[school-staff] Section markup is incomplete.");
    return;
  }

  section.dataset.schoolStaffInitialized = "true";
  if (state.modal.parentElement !== document.body) {
    document.body.append(state.modal);
  }

  [state.modalPhoto, state.previousPreviewImage, state.nextPreviewImage].forEach(configureModalImage);
  state.previousButton.append(createSvgIcon("chevronLeft"));
  state.nextButton.append(createSvgIcon("chevronRight"));
  state.closeButton.append(createSvgIcon("close"));

  state.filterButtons.forEach((button) => {
    button.addEventListener("click", () => selectFilter(state, button));
  });

  state.previousButton.addEventListener("click", () => scrollSlider(state, -1));
  state.nextButton.addEventListener("click", () => scrollSlider(state, 1));
  state.slider.addEventListener("scroll", () => updateSliderControls(state), { passive: true });

  if ("ResizeObserver" in window) {
    const resizeObserver = new ResizeObserver(() => updateSliderControls(state));
    resizeObserver.observe(state.slider);
  } else {
    window.addEventListener("resize", () => updateSliderControls(state), { passive: true });
  }

  bindModalEvents(state);
  loadStaff(state);
}

export function initModernStaffSection() {
  document.querySelectorAll(STAFF_SECTION_SELECTOR).forEach(initSection);
}
