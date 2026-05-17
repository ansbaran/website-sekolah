const HEADER_SELECTOR = "[data-header]";
const NAV_TOGGLE_SELECTOR = "[data-nav-toggle]";
const NAV_MENU_SELECTOR = "[data-nav-menu]";
const NAV_LINK_SELECTOR =".nav-menu a, .dropdown-menu a";
const HEADER_SCROLLED_CLASS = "site-header--scrolled";
const NAV_OPEN_CLASS = "navbar__menu--open";
const AUTO_REVEAL_SELECTOR = [
  "main > section",
  "body > section",
  ".prestasi-hero",
  ".news-heading",
  ".news-section",
  ".news-card",
  ".gallery-header",
  ".gallery-card",
  ".simple-gallery-header",
  ".simple-gallery-card",
  ".activity-card",
  ".activity-strip"
].join(", ");

/* =========================
   HEADER SCROLL EFFECT
========================= */
export function initHeaderState() {
  const header = document.querySelector(HEADER_SELECTOR);

  if (!header) return;

  const updateHeader = () => {
    const isScrolled = window.scrollY > 30;
    header.classList.toggle(HEADER_SCROLLED_CLASS, isScrolled);
  };

  updateHeader();
  window.addEventListener("scroll", updateHeader, { passive: true });
}

/* =========================
   MOBILE NAVIGATION
========================= */
export function initMobileNavigation() {
  const navToggle = document.querySelector(NAV_TOGGLE_SELECTOR);
  const navMenu = document.querySelector(NAV_MENU_SELECTOR);

  if (!navToggle || !navMenu) return;

  const closeMenu = () => {
    navMenu.classList.remove(NAV_OPEN_CLASS);
    navToggle.setAttribute("aria-expanded", "false");
  };

  navToggle.addEventListener("click", () => {
    const isOpen = navMenu.classList.toggle(NAV_OPEN_CLASS);
    navToggle.setAttribute("aria-expanded", String(isOpen));
  });

  navMenu.querySelectorAll(NAV_LINK_SELECTOR).forEach((link) => {
    link.addEventListener("click", closeMenu);
  });
}
export function initDropdownHover() {
  const dropdowns = document.querySelectorAll(".dropdown");

  dropdowns.forEach((dropdown) => {
    const toggle = dropdown.querySelector(".dropdown-toggle");

    dropdown.addEventListener("mouseenter", () => {
      dropdown.classList.add("open");
    });

    dropdown.addEventListener("mouseleave", () => {
      dropdown.classList.remove("open");
    });

    toggle?.addEventListener("click", () => {
      dropdown.classList.toggle("open");
    });
  });
}

/* =========================
   HASH SECTION NAVIGATION
========================= */
export function initHashSectionNavigation() {
  if (window.__HASH_NAV_BOUND__) return;
  window.__HASH_NAV_BOUND__ = true;

  const OFFSET = 110;

  const scrollToTarget = (hash) => {
    const target = document.querySelector(hash);
    if (!target) return;

    const y = target.getBoundingClientRect().top + window.scrollY - OFFSET;

    window.scrollTo({
      top: y,
      behavior: "smooth"
    });
  };

  // 🔥 HANDLE CLICK LANGSUNG
  document.addEventListener("click", (event) => {
    const link = event.target.closest('a[href*="#"]');
    if (!link) return;

    const url = new URL(link.href);
    const hash = url.hash;

    if (window.location.pathname === url.pathname && hash) {
      event.preventDefault();
      history.pushState(null, "", hash);
      scrollToTarget(hash);
    }
  });

  document.querySelectorAll("[data-legacy-hash-disabled]").forEach((link) => {
    link.addEventListener("click", (e) => {
      const url = new URL(link.href);
      const hash = url.hash;

      // hanya jika di halaman yang sama
      if (window.location.pathname === url.pathname && hash) {
        e.preventDefault();
        history.pushState(null, "", hash);
        scrollToTarget(hash);
      }
    });
  });

  // saat load
  window.addEventListener("load", () => {
    if (window.location.hash) {
      scrollToTarget(window.location.hash);
    }
  });

  // saat hash berubah
  window.addEventListener("hashchange", () => {
    scrollToTarget(window.location.hash);
  });
}
export function initReveal() {
  if (window.__AOS_MANAGED__) {
    document.querySelectorAll(".reveal, .reveal-stagger").forEach((el) => {
      el.classList.add("active");

      if (el.classList.contains("reveal-stagger")) {
        Array.from(el.children).forEach((child) => child.classList.add("active"));
      }
    });
  } else {
    document.querySelectorAll(AUTO_REVEAL_SELECTOR).forEach((el) => {
      if (!el.classList.contains("reveal") && !el.classList.contains("reveal-stagger")) {
        el.classList.add("scroll-fade");
      }
    });
  }

  const elements = window.__AOS_MANAGED__
    ? []
    : document.querySelectorAll('.reveal, .reveal-stagger, .scroll-fade');

  if (elements.length && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        entry.target.classList.add("active");

        if (entry.target.classList.contains("reveal-stagger")) {
          Array.from(entry.target.children).forEach((child, i) => {
            setTimeout(() => {
              child.classList.add("active");
            }, i * 120);
          });
        }

        observer.unobserve(entry.target);
      });
    }, {
      threshold: 0.12,
      rootMargin: "0px 0px -60px"
    });

    elements.forEach((el) => observer.observe(el));
  }

  function revealOnScroll() {
    const windowHeight = window.innerHeight;

    elements.forEach((el) => {
      const rect = el.getBoundingClientRect();

      if (rect.top < windowHeight - 100) {
        el.classList.add('active');

        // stagger children
        if (el.classList.contains('reveal-stagger')) {
          Array.from(el.children).forEach((child, i) => {
            setTimeout(() => {
              child.classList.add('active');
            }, i * 120);
          });
        }
      }
    });
  }

  function bindScrollTopBtnOnce(scrollBtn) {
    if (!scrollBtn || scrollBtn.dataset.bound === "1") return;
    scrollBtn.dataset.bound = "1";

    // muncul saat scroll
    window.addEventListener("scroll", () => {
      if (window.scrollY > 300) {
        scrollBtn.classList.add("show");
      } else {
        scrollBtn.classList.remove("show");
      }
    }, { passive: true });

    // klik → scroll ke atas
    scrollBtn.addEventListener("click", () => {
      window.scrollTo({
        top: 0,
        behavior: "smooth"
      });
    });
  }

  // Tombol bisa di-inject setelah JS init (footer loader)
  const existingBtn = document.getElementById("scrollTopBtn");
  if (existingBtn) {
    bindScrollTopBtnOnce(existingBtn);
  } else {
    const observer = new MutationObserver(() => {
      const btn = document.getElementById("scrollTopBtn");
      if (btn) {
        bindScrollTopBtnOnce(btn);
        observer.disconnect();
      }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  if (elements.length && !("IntersectionObserver" in window)) {
    window.addEventListener('load', revealOnScroll);
    window.addEventListener('scroll', revealOnScroll, { passive: true });
  }
}
/* =========================
   ACTIVE MENU
========================= */

/* =========================
   ACTIVE MENU
========================= */

/* =========================
   ACTIVE MENU
========================= */

/* =========================
   ACTIVE MENU
========================= */

export function initActiveMenu() {

    // halaman aktif
    const currentPage =
        window.location.pathname.split("/").pop() || "index.html";

    const activityPages = [
        "prestasi.html",
        "galeri.html",
        "kegiatan.html",
        "ekstrakurikuler.html"
    ];

    // hanya menu utama navbar
    const navLinks =
        document.querySelectorAll(".nav-menu > li > a");

    navLinks.forEach((link) => {

        // reset semua
        link.classList.remove("active");

        const href =
            link.getAttribute("href");

        if (!href) return;

        // ambil nama file tanpa hash
        const cleanHref =
            href.split("#")[0];

        // cocokkan halaman
        if (
            cleanHref === currentPage ||
            (activityPages.includes(currentPage) && link.textContent.trim() === "Aktivitas")
        ) {

            link.classList.add("active");

        }
    });
}
