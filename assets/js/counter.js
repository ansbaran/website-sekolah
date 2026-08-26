const publicStatsFallback = {
  students_count: 500,
  achievements_year_count: 50,
  educators_count: 25,
  featured_programs_count: 15,
  teachers_count: 25,
  staff_count: 6,
  accreditation_label: "A",
  professional_label: "A+"
};

export function initCounter() {
  applyPublicStats(publicStatsFallback);

  fetchPublicStats().then((stats) => {
    applyPublicStats(stats);
    initAnimatedCounters();
  });

  initPpdbCountdown();
}

function initAnimatedCounters() {
  const counters = document.querySelectorAll("[data-target]");

  if (!counters.length) {
    return;
  }

  const animate = (el, target) => {
    let current = 0;
    const speed = Math.max(target / 80, 1);
    const suffix = el.dataset.statSuffix ?? "+";

    function update() {
      current += speed;

      if (current < target) {
        el.textContent = `${Math.floor(current)}${suffix}`;
        requestAnimationFrame(update);
      } else {
        el.textContent = `${target}${suffix}`;
        el.dataset.animated = "true";
      }
    }

    update();
  };

  const reset = (el) => {
    const suffix = el.dataset.statSuffix ?? "+";
    el.textContent = `0${suffix}`;
    el.dataset.animated = "false";
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const el = entry.target;
      const target = Number(el.dataset.target || 0);

      if (!Number.isFinite(target) || target < 0) {
        return;
      }

      if (entry.isIntersecting) {
        if (el.dataset.animated !== "true") {
          animate(el, target);
        }
      } else {
        reset(el);
      }
    });
  }, {
    threshold: 0.6
  });

  counters.forEach((el) => {
    if (el.dataset.counterObserved === "true") {
      return;
    }

    el.dataset.counterObserved = "true";
    el.dataset.animated = "false";
    observer.observe(el);
  });
}

function applyPublicStats(stats) {
  const mergedStats = {
    ...publicStatsFallback,
    ...stats
  };

  document.querySelectorAll("[data-public-stat-number]").forEach((el) => {
    const key = el.dataset.publicStatNumber;
    const value = normalizeCounterValue(mergedStats[key], Number(el.dataset.target || 0));
    const suffix = el.dataset.statSuffix ?? "+";

    el.dataset.target = String(value);
    el.textContent = `0${suffix}`;
    el.dataset.animated = "false";
  });

  document.querySelectorAll("[data-public-stat-text]").forEach((el) => {
    const key = el.dataset.publicStatText;
    const value = mergedStats[key];

    if (value === undefined || value === null) {
      return;
    }

    el.textContent = String(value);
  });
}

function normalizeCounterValue(value, fallback) {
  const number = Number(value);

  if (!Number.isFinite(number) || number < 0) {
    return Math.max(0, Math.floor(Number(fallback) || 0));
  }

  return Math.floor(number);
}

async function fetchPublicStats() {
  const controller = "AbortController" in window ? new AbortController() : null;
  const timeout = controller ? window.setTimeout(() => controller.abort(), 4500) : null;

  try {
    const response = await fetch(`${getBasePath()}/api/public-stats.php`, {
      headers: {
        Accept: "application/json"
      },
      cache: "no-store",
      signal: controller?.signal
    });

    if (!response.ok) {
      return publicStatsFallback;
    }

    const payload = await response.json();
    if (payload.status !== "success" || !payload.data) {
      return publicStatsFallback;
    }

    return {
      ...publicStatsFallback,
      ...payload.data
    };
  } catch {
    return publicStatsFallback;
  } finally {
    if (timeout) {
      window.clearTimeout(timeout);
    }
  }
}

const ppdbFallbackYear = new Date().getFullYear();

const ppdbFallback = {
  status: "open",
  year: String(ppdbFallbackYear),
  start_date: `${ppdbFallbackYear}-01-01`,
  end_date: `${ppdbFallbackYear}-12-31`,
  whatsapp_number: "6285692890015",
  whatsapp_message: "Halo, saya ingin mendaftar PPDB SD Cahaya Harapan Bekasi.",
  whatsapp_url: "https://wa.me/6285692890015?text=Halo%2C%20saya%20ingin%20mendaftar%20PPDB%20SD%20Cahaya%20Harapan%20Bekasi.",
  description: ""
};

function getBasePath() {
  const marker = "/website-sekolah";
  return window.location.pathname.includes(marker) ? marker : "";
}

function parseLocalDate(dateString, endOfDay = false) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(String(dateString || ""))) {
    return null;
  }

  const suffix = endOfDay ? "T23:59:59" : "T00:00:00";
  const date = new Date(`${dateString}${suffix}`);

  return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateRange(startDate, endDate) {
  if (!startDate || !endDate) {
    return "Periode PPDB akan diinformasikan oleh sekolah.";
  }

  const formatter = new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "long",
    year: "numeric"
  });

  return `Periode ${formatter.format(startDate)} - ${formatter.format(endDate)}. Konsultasi dan pendaftaran langsung bersama tim SD Cahaya Harapan Bekasi.`;
}

function safeWhatsappUrl(settings) {
  const number = String(settings.whatsapp_number || ppdbFallback.whatsapp_number).replace(/\D/g, "");
  const message = String(settings.whatsapp_message || ppdbFallback.whatsapp_message);

  if (!number) {
    return ppdbFallback.whatsapp_url;
  }

  return `https://wa.me/${number}?text=${encodeURIComponent(message)}`;
}

async function fetchPpdbSettings() {
  const controller = "AbortController" in window ? new AbortController() : null;
  const timeout = controller ? window.setTimeout(() => controller.abort(), 4500) : null;

  try {
    const response = await fetch(`${getBasePath()}/api/public-ppdb.php`, {
      headers: {
        Accept: "application/json"
      },
      cache: "no-store",
      signal: controller?.signal
    });

    if (!response.ok) {
      return ppdbFallback;
    }

    const payload = await response.json();
    if (payload.status !== "success" || !payload.data) {
      return ppdbFallback;
    }

    return {
      ...ppdbFallback,
      ...payload.data
    };
  } catch {
    return ppdbFallback;
  } finally {
    if (timeout) {
      window.clearTimeout(timeout);
    }
  }
}

function setCountdownValues(elements, days, hours, minutes, seconds) {
  elements.days.textContent = String(Math.max(0, days));
  elements.hours.textContent = String(Math.max(0, hours));
  elements.minutes.textContent = String(Math.max(0, minutes));
  elements.seconds.textContent = String(Math.max(0, seconds));
}

function updatePpdbText(settings, phase, startDate, endDate) {
  const year = String(settings.year || ppdbFallback.year);
  const titleEl = document.querySelector("[data-ppdb-title]");
  const eyebrowEl = document.querySelector("[data-ppdb-eyebrow]");
  const periodEl = document.querySelector("[data-ppdb-period]");
  const noteEl = document.querySelector("[data-ppdb-note]");
  const whatsappLink = document.querySelector("[data-ppdb-whatsapp]");

  if (eyebrowEl) {
    eyebrowEl.textContent = `PPDB ${year}`;
  }

  if (titleEl) {
    titleEl.textContent = phase === "closed"
      ? "Pendaftaran Peserta Didik Baru Telah Ditutup"
      : phase === "upcoming"
        ? "Pendaftaran Peserta Didik Baru Segera Dibuka"
        : "Pendaftaran Peserta Didik Baru Telah Dibuka";
  }

  if (periodEl) {
    periodEl.textContent = settings.description || formatDateRange(startDate, endDate);
  }

  if (noteEl) {
    noteEl.textContent = phase === "closed"
      ? "Silakan hubungi admin sekolah untuk informasi gelombang berikutnya."
      : phase === "upcoming"
        ? "Siapkan berkas dan konsultasikan kebutuhan putra-putri Anda dengan tim sekolah."
        : "Pendaftaran terbatas, segera amankan tempat.";
  }

  if (whatsappLink) {
    whatsappLink.href = safeWhatsappUrl(settings);
  }
}

function initPpdbCountdown() {
  const elements = {
    countdown: document.getElementById("countdown"),
    label: document.querySelector("[data-countdown-label]"),
    days: document.getElementById("days"),
    hours: document.getElementById("hours"),
    minutes: document.getElementById("minutes"),
    seconds: document.getElementById("seconds")
  };

  if (!elements.countdown || !elements.label || !elements.days || !elements.hours || !elements.minutes || !elements.seconds) {
    return;
  }

  fetchPpdbSettings().then((settings) => {
    const startDate = parseLocalDate(settings.start_date);
    const endDate = parseLocalDate(settings.end_date, true);

    const render = () => {
      const now = new Date();
      let phase = "open";
      let targetDate = endDate;
      let label = "Pendaftaran ditutup dalam:";

      if (settings.status === "closed" || !startDate || !endDate || now > endDate) {
        phase = "closed";
        label = "Pendaftaran telah ditutup";
        setCountdownValues(elements, 0, 0, 0, 0);
      } else if (now < startDate) {
        phase = "upcoming";
        targetDate = startDate;
        label = "Pendaftaran dibuka dalam:";
      }

      elements.label.textContent = label;
      updatePpdbText(settings, phase, startDate, endDate);

      if (phase === "closed") {
        return;
      }

      const distance = targetDate.getTime() - now.getTime();

      if (!Number.isFinite(distance) || distance <= 0) {
        setCountdownValues(elements, 0, 0, 0, 0);
        return;
      }

      setCountdownValues(
        elements,
        Math.floor(distance / (1000 * 60 * 60 * 24)),
        Math.floor((distance / (1000 * 60 * 60)) % 24),
        Math.floor((distance / (1000 * 60)) % 60),
        Math.floor((distance / 1000) % 60)
      );
    };

    window.clearInterval(window.__ppdbCountdownTimer);
    render();
    window.__ppdbCountdownTimer = window.setInterval(render, 1000);
  });
}
