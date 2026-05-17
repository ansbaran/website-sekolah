const AOS_CSS_URL = "https://unpkg.com/aos@2.3.4/dist/aos.css";
const AOS_JS_URL = "https://unpkg.com/aos@2.3.4/dist/aos.js";

const AOS_OPTIONS = {
  once: false,
  mirror: true,
  duration: 780,
  easing: "ease-out-cubic",
  offset: 48,
  debounceDelay: 40,
  throttleDelay: 60,
  disableMutationObserver: true,
  anchorPlacement: "top-bottom"
};

const ANIMATION_RULES = [
  { selector: ".hero, .prestasi-hero", animation: "zoom-out", duration: 1100, delay: 0 },
  { selector: "main > section, body > section", animation: "fade-up", duration: 780, delay: 0 },
  { selector: ".section-heading, .simple-gallery-header, .news-heading, .team-heading, .history-heading", animation: "fade-up", duration: 720, delay: 0 },
  { selector: ".card, .news-card, .gallery-card, .simple-gallery-card, .activity-card, .leader-card, .stat-box", animation: "fade-up", duration: 720, stagger: 35 },
  { selector: ".media-card, .card-image, .simple-gallery-card img", animation: "zoom-in", duration: 760, stagger: 25 },
  { selector: ".vm-block, .vm-list-item", animation: "fade-right", duration: 740, stagger: 35 },
  { selector: ".history-box.top", animation: "fade-right", duration: 760, stagger: 35 },
  { selector: ".history-box.bottom", animation: "fade-left", duration: 760, stagger: 35 }
];

window.__AOS_MANAGED__ = true;

function addResourceHints() {
  if (document.querySelector('link[data-aos-preconnect="true"]')) return;

  const preconnect = document.createElement("link");
  preconnect.rel = "preconnect";
  preconnect.href = "https://unpkg.com";
  preconnect.crossOrigin = "anonymous";
  preconnect.dataset.aosPreconnect = "true";
  document.head.appendChild(preconnect);
}

function loadStylesheet(href) {
  if (document.querySelector(`link[href="${href}"]`)) {
    return Promise.resolve();
  }

  return new Promise((resolve, reject) => {
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = href;
    link.onload = resolve;
    link.onerror = reject;
    document.head.appendChild(link);
  });
}

function loadScript(src) {
  if (window.AOS) return Promise.resolve();

  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = src;
    script.defer = true;
    script.onload = resolve;
    script.onerror = reject;
    document.head.appendChild(script);
  });
}

function setAosAttribute(element, name, value) {
  if (element.dataset.aosCustom === "true" && element.hasAttribute(name)) return;

  element.setAttribute(name, String(value));
}

function applyAnimationRules(root = document) {
  ANIMATION_RULES.forEach((rule) => {
    root.querySelectorAll(rule.selector).forEach((element, index) => {
      setAosAttribute(element, "data-aos", rule.animation);
      setAosAttribute(element, "data-aos-duration", rule.duration);

      const delay = rule.delay ?? Math.min(index * (rule.stagger ?? 0), 120);
      setAosAttribute(element, "data-aos-delay", delay);
    });
  });
}

function optimizeImages(root = document) {
  root.querySelectorAll("img").forEach((image) => {
    if (!image.hasAttribute("loading")) {
      image.setAttribute("loading", "lazy");
    }

    if (!image.hasAttribute("decoding")) {
      image.setAttribute("decoding", "async");
    }
  });
}

function markRevealContentVisible(root = document) {
  root.querySelectorAll(".reveal, .reveal-stagger").forEach((element) => {
    element.classList.add("active");

    if (element.classList.contains("reveal-stagger")) {
      Array.from(element.children).forEach((child) => child.classList.add("active"));
    }
  });
}

function initFallbackAnimations() {
  document.documentElement.classList.add("aos-fallback");

  const elements = document.querySelectorAll("[data-aos]");

  if (!("IntersectionObserver" in window)) {
    elements.forEach((element) => element.classList.add("aos-fallback-visible"));
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      entry.target.classList.toggle("aos-fallback-visible", entry.isIntersecting);
    });
  }, {
    threshold: 0.12,
    rootMargin: "0px 0px -60px"
  });

  elements.forEach((element) => observer.observe(element));
}

function refreshAos({ hard = false } = {}) {
  applyAnimationRules();
  optimizeImages();
  markRevealContentVisible();

  if (window.AOS) {
    if (hard) {
      window.AOS.refreshHard();
    } else {
      window.AOS.refresh();
    }
  }
}

function observeDynamicContent() {
  if (!("MutationObserver" in window)) return;

  let refreshTimer;

  const observer = new MutationObserver(() => {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => refreshAos({ hard: false }), 180);
  });

  observer.observe(document.body, {
    childList: true,
    subtree: true
  });
}

function bindNavigationRefresh() {
  window.addEventListener("load", refreshAos);
  window.addEventListener("hashchange", () => {
    window.setTimeout(refreshAos, 420);
  });
  window.addEventListener("popstate", () => {
    window.setTimeout(refreshAos, 180);
  });
}

export async function initAosAnimations() {
  addResourceHints();
  applyAnimationRules();
  optimizeImages();
  markRevealContentVisible();
  bindNavigationRefresh();
  observeDynamicContent();

  try {
    await Promise.all([
      loadStylesheet(AOS_CSS_URL),
      loadScript(AOS_JS_URL)
    ]);

    if (!window.AOS) {
      throw new Error("AOS unavailable");
    }

    window.AOS.init(AOS_OPTIONS);
    window.AOS.refresh();
    document.documentElement.classList.add("aos-ready");
  } catch (error) {
    console.warn("AOS gagal dimuat, memakai fallback animasi ringan.", error);
    initFallbackAnimations();
  }
}
