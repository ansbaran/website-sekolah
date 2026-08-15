const FLOATING_WHATSAPP_SELECTOR = "[data-floating-whatsapp]";
const DEFAULT_WHATSAPP_NUMBER = "6285692890015";
const DEFAULT_WHATSAPP_MESSAGE = "Halo SD Cahaya Harapan Bekasi, saya ingin mendapatkan informasi lebih lanjut.";
const FALLBACK_WHATSAPP_URL = "https://wa.me/6285692890015?text=Halo%20SD%20Cahaya%20Harapan%20Bekasi%2C%20saya%20ingin%20mendapatkan%20informasi%20lebih%20lanjut.";
const API_TIMEOUT_MS = 4500;

let initialized = false;
let pendingRequest = null;

function getBasePath() {
  const base = document.querySelector("base")?.getAttribute("href");
  if (base) {
    return new URL(base, window.location.href).pathname.replace(/\/$/, "");
  }

  const script = document.querySelector('script[src*="main.js"], script[src*="floating-whatsapp.js"]');
  if (script) {
    const scriptUrl = new URL(script.getAttribute("src"), window.location.href);
    const marker = "/assets/js/";
    const markerIndex = scriptUrl.pathname.indexOf(marker);
    if (markerIndex > 0) {
      return scriptUrl.pathname.slice(0, markerIndex);
    }
  }

  const marker = "/website-sekolah";
  return window.location.pathname.includes(marker) ? marker : "";
}

function normalizePhoneNumber(value) {
  const digits = String(value || "").replace(/[^\d]/g, "");
  if (!digits) return "";

  if (digits.startsWith("0")) {
    return `62${digits.slice(1)}`;
  }

  if (digits.startsWith("62")) {
    return digits;
  }

  if (digits.startsWith("8")) {
    return `62${digits}`;
  }

  return digits;
}

function buildWhatsAppUrl(number, message = DEFAULT_WHATSAPP_MESSAGE) {
  const phone = normalizePhoneNumber(number);
  if (phone.length < 8 || phone.length > 16) {
    return "";
  }

  const cleanMessage = String(message || DEFAULT_WHATSAPP_MESSAGE).trim() || DEFAULT_WHATSAPP_MESSAGE;
  return `https://wa.me/${phone}?text=${encodeURIComponent(cleanMessage)}`;
}

function hasCampaignMessage(url) {
  const text = url.searchParams.get("text") || "";
  return /ppdb|20\d{2}/i.test(text);
}

function normalizeWhatsAppUrl(value) {
  if (!value) return "";

  const raw = String(value).trim();
  const phoneOnly = normalizePhoneNumber(raw);
  if (/^[+\d\s().-]+$/.test(raw) && phoneOnly) {
    return buildWhatsAppUrl(phoneOnly);
  }

  try {
    const url = new URL(raw, window.location.href);
    if (url.protocol !== "https:") return "";

    const host = url.hostname.replace(/^www\./, "").toLowerCase();
    const isWhatsAppHost = host === "wa.me" || host === "api.whatsapp.com" || host === "whatsapp.com";
    if (!isWhatsAppHost) return "";

    if (hasCampaignMessage(url)) {
      const phoneFromPath = normalizePhoneNumber(url.pathname);
      const phoneFromQuery = normalizePhoneNumber(url.searchParams.get("phone"));
      return buildWhatsAppUrl(phoneFromPath || phoneFromQuery || DEFAULT_WHATSAPP_NUMBER);
    }

    if (!url.searchParams.has("text")) {
      url.searchParams.set("text", DEFAULT_WHATSAPP_MESSAGE);
    }

    return url.href;
  } catch {
    return "";
  }
}

function resolveConfigUrl() {
  return new URL(`${getBasePath()}/api/public-ppdb.php`, window.location.href);
}

async function fetchWhatsAppUrl() {
  const controller = "AbortController" in window ? new AbortController() : null;
  const timeout = controller ? window.setTimeout(() => controller.abort(), API_TIMEOUT_MS) : null;

  try {
    const response = await fetch(resolveConfigUrl(), {
      cache: "no-store",
      headers: { Accept: "application/json" },
      signal: controller?.signal
    });

    if (!response.ok) return "";

    const payload = await response.json();
    const data = payload?.data || payload || {};
    const directUrl = normalizeWhatsAppUrl(data.wa_link || data.whatsapp_url || data.whatsapp_link);
    if (directUrl) return directUrl;

    const numberUrl = buildWhatsAppUrl(data.whatsapp_number || data.wa_number || data.phone || DEFAULT_WHATSAPP_NUMBER);
    return numberUrl || "";
  } catch {
    return "";
  } finally {
    if (timeout) {
      window.clearTimeout(timeout);
    }
  }
}

function bindButton(button) {
  if (!button || button.dataset.floatingWhatsappBound === "1") return;
  button.dataset.floatingWhatsappBound = "1";
  button.href = FALLBACK_WHATSAPP_URL;

  if (!pendingRequest) {
    pendingRequest = fetchWhatsAppUrl();
  }

  pendingRequest.then((url) => {
    button.href = url || FALLBACK_WHATSAPP_URL;
  });
}

function initFloatingWhatsApp() {
  if (initialized) return;
  initialized = true;

  const bindExistingButton = () => {
    const button = document.querySelector(FLOATING_WHATSAPP_SELECTOR);
    if (!button) return false;
    bindButton(button);
    return true;
  };

  if (bindExistingButton()) return;

  const observer = new MutationObserver(() => {
    if (bindExistingButton()) {
      observer.disconnect();
    }
  });

  observer.observe(document.documentElement, { childList: true, subtree: true });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initFloatingWhatsApp, { once: true });
} else {
  initFloatingWhatsApp();
}

export { initFloatingWhatsApp, normalizeWhatsAppUrl };
