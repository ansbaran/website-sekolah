const STAFF_SECTION_SELECTOR = "[data-staff-section]";

function getBasePath() {
  const base = document.querySelector("base")?.getAttribute("href");
  if (base) {
    return new URL(base, window.location.href).pathname.replace(/\/$/, "");
  }

  const marker = "/website-sekolah";
  return window.location.pathname.includes(marker) ? marker : "";
}

function escapeHtml(value) {
  return String(value || "").replace(/[&<>"']/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;"
  })[char]);
}

function resolveImage(path) {
  if (!path) {
    return `${getBasePath()}/assets/img/logo.png`;
  }

  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const basePath = getBasePath();
  let cleanPath = String(path).replace(/^\/+/, "");
  const baseSegment = `${basePath.replace(/^\/+/, "")}/`;
  if (basePath && cleanPath.startsWith(baseSegment)) {
    cleanPath = cleanPath.slice(baseSegment.length);
  }

  return `${basePath}/${cleanPath}`;
}

async function fetchStaff(limit = 24) {
  const url = new URL(`${getBasePath()}/api/public-staff.php`, window.location.href);
  url.searchParams.set("limit", String(limit));

  const response = await fetch(url, { headers: { Accept: "application/json" } });
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }

  const payload = await response.json();
  return payload.status === "success" ? payload.data : [];
}

function safeHttpUrl(value) {
  if (!value) return "";
  try {
    const url = new URL(String(value), window.location.href);
    return /^https?:$/i.test(url.protocol) ? url.href : "";
  } catch {
    return "";
  }
}

function buildStaffContactLinks(item) {
  const links = [];

  if (item.email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(item.email))) {
    links.push({ label: "Email", href: `mailto:${item.email}`, external: false });
  }

  if (item.whatsapp) {
    const phone = String(item.whatsapp).replace(/[^\d]/g, "");
    if (phone.length >= 8 && phone.length <= 16) {
      links.push({ label: "WhatsApp", href: `https://wa.me/${phone}`, external: true });
    }
  }

  [
    ["Instagram", item.instagram],
    ["Facebook", item.facebook],
    ["TikTok", item.tiktok],
    ["YouTube", item.youtube],
    ["Website", item.website]
  ].forEach(([label, value]) => {
    const href = safeHttpUrl(value);
    if (href) {
      links.push({ label, href, external: true });
    }
  });

  return links;
}

function renderStaffContactLinks(container, item) {
  if (!container) return;

  container.replaceChildren();
  const links = buildStaffContactLinks(item);

  if (!links.length) {
    const empty = document.createElement("p");
    empty.className = "staff-modal__empty-contact";
    empty.textContent = "Informasi kontak publik belum tersedia.";
    container.appendChild(empty);
    return;
  }

  links.forEach((link) => {
    const anchor = document.createElement("a");
    const label = document.createElement("span");
    anchor.className = "staff-modal__link";
    anchor.href = link.href;
    anchor.setAttribute("aria-label", `Buka ${link.label} ${item.nama || "staff"}`);
    if (link.external) {
      anchor.target = "_blank";
      anchor.rel = "noopener noreferrer";
    }
    label.textContent = link.label;
    anchor.appendChild(label);
    container.appendChild(anchor);
  });
}

function perPage() {
  if (window.matchMedia("(min-width: 1024px)").matches) return 4;
  if (window.matchMedia("(min-width: 768px)").matches) return 3;
  if (window.matchMedia("(min-width: 560px)").matches) return 2;
  return 1;
}

function openModal(section, item) {
  const modal = section.querySelector("[data-staff-modal]");
  if (!modal) return;

  modal.querySelector("[data-staff-modal-image]").src = resolveImage(item.foto);
  modal.querySelector("[data-staff-modal-image]").alt = item.nama;
  modal.querySelector("[data-staff-modal-name]").textContent = item.nama;
  modal.querySelector("[data-staff-modal-role]").textContent = item.jabatan;
  const descriptionElement = modal.querySelector("[data-staff-modal-description]");
  if (descriptionElement) {
    descriptionElement.textContent = item.deskripsi || "";
    descriptionElement.hidden = !item.deskripsi;
  }
  renderStaffContactLinks(modal.querySelector("[data-staff-modal-links]"), item);

  modal.hidden = false;
  document.body.classList.add("modal-open");
  modal.querySelector("[data-staff-modal-close]")?.focus();
}

function closeModal(section) {
  const modal = section.querySelector("[data-staff-modal]");
  if (!modal) return;
  modal.hidden = true;
  document.body.classList.remove("modal-open");
}

function renderStaff(section, items) {
  const track = section.querySelector("[data-staff-track]");
  const status = section.querySelector("[data-staff-status]");
  const viewport = section.querySelector(".staff-slider__viewport");
  const controls = section.querySelector(".staff-slider__controls");
  if (!track) return;

  if (!items.length) {
    status.textContent = "";
    track.innerHTML = `
      <div class="staff-empty-state">
        <strong>Data Guru dan Staff akan segera tersedia.</strong>
        <span>Tim sekolah sedang melengkapi profil tenaga pendidik dan kependidikan.</span>
      </div>
    `;
    return;
  }

  status.textContent = "";
  const cardMarkup = items.map((item, index) => {
    const hasPhoto = Boolean(item.foto);

    return `
    <button class="staff-card${hasPhoto ? "" : " staff-card--no-photo"}" type="button" data-staff-index="${index}" aria-label="Lihat profil ${escapeHtml(item.nama)}">
      <span class="staff-card__photo" aria-hidden="true">
        <img src="${resolveImage(item.foto)}" alt="${escapeHtml(item.nama)}" loading="lazy">
      </span>
      <span class="staff-card__body">
        <strong>${escapeHtml(item.nama)}</strong>
        <span class="staff-card__role">${escapeHtml(item.jabatan)}</span>
        ${item.deskripsi ? `<span class="staff-card__description">${escapeHtml(item.deskripsi)}</span>` : ""}
        <span class="staff-card__profile">Lihat Profil</span>
      </span>
    </button>
  `;
  }).join("");
  track.innerHTML = cardMarkup;

  let userPaused = false;
  let interactionPaused = false;
  let animationFrame = 0;
  let lastTime = 0;
  let loopWidth = 0;
  const speed = 34;
  const shouldAnimate = () => viewport && track.scrollWidth > viewport.clientWidth + 8;


  const slider = section.querySelector(".staff-slider");
  const ensureRailButton = (direction) => {
    if (!slider) return null;

    const selector = direction === "prev" ? "[data-staff-prev-rail]" : "[data-staff-next-rail]";
    const existing = slider.querySelector(selector);
    if (existing) return existing;

    const button = document.createElement("button");
    button.className = `staff-slider__rail-button staff-slider__rail-button--${direction}`;
    button.type = "button";
    button.textContent = direction === "prev" ? "<" : ">";
    button.setAttribute("aria-label", direction === "prev" ? "Geser Guru dan Staff ke kiri" : "Geser Guru dan Staff ke kanan");

    if (direction === "prev") {
      button.dataset.staffPrev = "";
      button.dataset.staffPrevRail = "";
    } else {
      button.dataset.staffNext = "";
      button.dataset.staffNextRail = "";
    }

    slider.appendChild(button);
    return button;
  };

  ensureRailButton("prev");
  ensureRailButton("next");


  const pauseForInteraction = () => {
    interactionPaused = true;
  };

  const resumeAfterInteraction = () => {
    interactionPaused = false;
    lastTime = performance.now();
  };

  const syncLoop = () => {
    if (!viewport || !shouldAnimate()) {
      track.innerHTML = cardMarkup;
      track.dataset.loopDuplicated = "0";
      loopWidth = 0;
      return;
    }

    if (track.dataset.loopDuplicated !== "1") {
      track.insertAdjacentHTML("beforeend", cardMarkup);
      track.dataset.loopDuplicated = "1";
    }

    const firstDuplicate = track.children[items.length];
    loopWidth = firstDuplicate ? firstDuplicate.offsetLeft : track.scrollWidth / 2;
  };

  const step = (time) => {
    if (!viewport) return;
    if (!lastTime) lastTime = time;
    const delta = time - lastTime;
    lastTime = time;

    if (shouldAnimate() && !userPaused && !interactionPaused && document.visibilityState === "visible") {
      viewport.scrollLeft += (speed * delta) / 1000;
      if (loopWidth > 0 && viewport.scrollLeft >= loopWidth) {
        viewport.scrollLeft -= loopWidth;
      }
    }

    animationFrame = requestAnimationFrame(step);
  };

  const update = () => {
    syncLoop();
    const visibleCount = perPage();
    track.classList.toggle("staff-slider__track--center", items.length < visibleCount || !shouldAnimate());
    section.querySelectorAll("[data-staff-prev], [data-staff-next]").forEach((button) => {
      button.hidden = !shouldAnimate();
      button.setAttribute("aria-hidden", shouldAnimate() ? "false" : "true");
      button.setAttribute("tabindex", shouldAnimate() ? "0" : "-1");
    });
  };

  section.querySelectorAll("[data-staff-prev]").forEach((button) => {
    button.addEventListener("click", () => {
      userPaused = true;
      viewport?.scrollBy({ left: -360, behavior: "smooth" });
    });
  });

  section.querySelectorAll("[data-staff-next]").forEach((button) => {
    button.addEventListener("click", () => {
      userPaused = true;
      viewport?.scrollBy({ left: 360, behavior: "smooth" });
    });
  });


  [viewport, track].filter(Boolean).forEach((element) => {
    element.addEventListener("pointerdown", pauseForInteraction);
    element.addEventListener("pointerup", resumeAfterInteraction);
    element.addEventListener("pointercancel", resumeAfterInteraction);
    element.addEventListener("mouseenter", pauseForInteraction);
    element.addEventListener("mouseleave", resumeAfterInteraction);
    element.addEventListener("focusin", pauseForInteraction);
    element.addEventListener("focusout", resumeAfterInteraction);
  });

  track.addEventListener("click", (event) => {
    const card = event.target.closest("[data-staff-index]");
    if (!card) return;
    userPaused = true;
    openModal(section, items[Number(card.dataset.staffIndex)]);
  });

  section.querySelector("[data-staff-modal-close]")?.addEventListener("click", () => closeModal(section));
  section.querySelector("[data-staff-modal]")?.addEventListener("click", (event) => {
    if (event.target.matches("[data-staff-modal]")) {
      closeModal(section);
    }
  });
  document.addEventListener("keydown", (event) => {
    const modal = section.querySelector("[data-staff-modal]");
    if (!modal || modal.hidden) return;

    if (event.key === "Escape") {
      closeModal(section);
      return;
    }

    if (event.key !== "Tab") {
      return;
    }

    const focusable = [...modal.querySelectorAll('a[href], button:not([disabled]), textarea, input, select, [tabindex]:not([tabindex="-1"])')]
      .filter((element) => element.offsetParent !== null);
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  window.addEventListener("resize", update, { passive: true });
  update();
  window.cancelAnimationFrame(animationFrame);
  animationFrame = requestAnimationFrame(step);
}
export async function initStaffSection() {
  const sections = document.querySelectorAll(STAFF_SECTION_SELECTOR);
  if (!sections.length) return;

  sections.forEach(async (section) => {
    const status = section.querySelector("[data-staff-status]");
    try {
      status.textContent = "Memuat data Guru & Staff...";
      const items = await fetchStaff(Number(section.dataset.staffLimit || 24));
      renderStaff(section, items);
    } catch {
      status.textContent = "Data Guru & Staff belum dapat dimuat.";
    }
  });
}
