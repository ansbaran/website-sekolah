const ABOUT_ENDPOINT = `${getBasePath()}/api/public-about.php`;

function getBasePath() {
  const marker = "/website-sekolah";
  return window.location.pathname.includes(marker) ? marker : "";
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

function createContactItem(iconClass, value) {
  const item = document.createElement("div");
  item.className = "contact-item";

  const icon = document.createElement("i");
  icon.className = iconClass;
  icon.setAttribute("aria-hidden", "true");

  const text = document.createElement("span");
  text.textContent = value;

  item.append(icon, text);
  return item;
}

function normalizeLeadershipTeam(data) {
  return parseJsonArray(data.leadership_team || data.leadershipTeam || data.team || data.leaders)
    .filter((member) => member && typeof member === "object")
    .filter((member) => member.active !== false && member.name && member.role);
}

function renderLeadership(team) {
  const grid = document.querySelector("[data-about-leadership-grid]");
  if (!grid || !Array.isArray(team) || !team.length) return;

  const variants = ["leader-blue", "leader-pink", "leader-green"];
  grid.replaceChildren();

  team.forEach((member, index) => {
    const card = document.createElement("article");
    card.className = `leader-card ${variants[index % variants.length]}`;

    const imageWrap = document.createElement("div");
    imageWrap.className = "leader-image";
    const image = document.createElement("img");
    image.src = resolveImage(member.image_url || member.image || member.photo, "assets/img/logo.png");
    image.alt = member.name || member.role || "Tim kepemimpinan sekolah";
    image.loading = "lazy";
    imageWrap.appendChild(image);

    const content = document.createElement("div");
    content.className = "leader-content";

    const name = document.createElement("h3");
    name.textContent = member.name || "Tim Kepemimpinan";

    const role = document.createElement("span");
    role.textContent = member.role || "Pimpinan Sekolah";

    const description = document.createElement("p");
    description.textContent = member.description || "Mendukung pengembangan sekolah dan pendampingan peserta didik.";

    const contact = document.createElement("div");
    contact.className = "leader-contact";
    if (member.email) contact.appendChild(createContactItem("fas fa-envelope", member.email));
    if (member.phone) contact.appendChild(createContactItem("fas fa-phone", member.phone));

    const line = document.createElement("div");
    line.className = "leader-line";

    content.append(name, role, description);
    if (contact.children.length) content.appendChild(contact);
    content.appendChild(line);
    card.append(imageWrap, content);
    grid.appendChild(card);
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
      renderLeadership(normalizeLeadershipTeam(data));
    })
    .catch(() => {});
}
