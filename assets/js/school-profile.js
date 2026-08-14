const ABOUT_ENDPOINT = `${getBasePath()}/api/public-about.php`;

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
  } catch {
    return "";
  }

  const path = window.location.pathname.replace(/\\/g, "/");
  const lastSlash = path.lastIndexOf("/");
  return lastSlash > 0 ? path.slice(0, lastSlash) : "";
}

function splitParagraphs(value) {
  return String(value || "")
    .split(/(?:\r?\n){2,}/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function parseJsonArray(value) {
  if (Array.isArray(value)) return value;
  if (!value || typeof value !== "string") return [];

  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return value
      .split(/\r?\n/)
      .map((item) => item.trim())
      .filter(Boolean);
  }
}

function resolveImage(value, fallback = "assets/img/logo.png") {
  const image = String(value || fallback);
  if (/^https?:\/\//i.test(image) || image.startsWith("/")) {
    return image;
  }
  return `${getBasePath()}/${image.replace(/^\/+/, "")}`;
}

function setText(selector, value) {
  const element = document.querySelector(selector);
  if (element && value) {
    element.textContent = value;
  }
}

function renderPrincipal(principal) {
  if (!principal) return;

  setText("[data-about-principal-badge]", principal.badge);
  setText("[data-about-principal-title]", principal.title);
  setText("[data-about-principal-name]", principal.name);
  setText("[data-about-principal-role]", principal.role);

  const messageContainer = document.querySelector("[data-about-principal-message]");
  if (messageContainer && principal.message) {
    messageContainer.replaceChildren();
    splitParagraphs(principal.message).forEach((paragraph) => {
      const item = document.createElement("p");
      item.className = "principal__text";
      item.textContent = paragraph;
      messageContainer.appendChild(item);
    });
  }

  const image = document.querySelector("[data-about-principal-image]");
  if (image && (principal.image_url || principal.image)) {
    image.src = resolveImage(principal.image_url || principal.image, image.getAttribute("src") || "assets/img/logo.png");
    image.alt = principal.name || "Kepala Sekolah";
    image.loading = "eager";
    image.decoding = "async";
    image.fetchPriority = "high";
  }
}

function renderVisionMission(data) {
  setText("[data-about-vision-text]", data.vision ? `"${String(data.vision).replace(/^"|"$/g, "")}"` : "");

  const list = document.querySelector("[data-about-mission-list]");
  const missions = parseJsonArray(data.missions || data.mission || data.about_missions);
  if (!list || !missions.length) return;

  list.replaceChildren();
  missions.forEach((mission, index) => {
    const item = document.createElement("div");
    item.className = "vm-list-item";

    const number = document.createElement("span");
    number.textContent = String(index + 1);

    const text = document.createElement("p");
    text.textContent = String(mission || "").trim();

    item.append(number, text);
    list.appendChild(item);
  });
}

async function fetchSchoolProfile() {
  const response = await fetch(ABOUT_ENDPOINT, {
    headers: { Accept: "application/json" },
    cache: "no-store"
  });
  if (!response.ok) return null;

  const payload = await response.json();
  return payload.status === "success" ? payload.data : null;
}

export function initSchoolProfileContent() {
  if (!document.querySelector("[data-about-page]")) return;

  fetchSchoolProfile()
    .then((data) => {
      if (!data) return;
      renderPrincipal(data.principal);
      renderVisionMission(data);
    })
    .catch(() => {});
}
