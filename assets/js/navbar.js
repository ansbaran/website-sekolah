async function loadNavbar() {
    const navbarContainer = document.getElementById("navbar");
    if (!navbarContainer) return;

    try {
        const baseUrl = window.location.pathname.includes('/website-sekolah/')
            ? '/website-sekolah/'
            : '/';
        const response = await fetch(baseUrl + 'components/navbar.html', { cache: 'no-cache' });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const html = await response.text();
        navbarContainer.innerHTML = html;
        initNavbar();
        document.dispatchEvent(new CustomEvent('navbar:loaded'));
    } catch (error) {
        console.error('Navbar gagal dimuat:', error);
    }
}

loadNavbar();

/* =========================
   ACTIVE MENU
========================= */

function setActiveMenu() {

    // halaman aktif
    const currentPage =
        window.location.pathname.split("/").pop() || "index.html";

    const activityPages = [
        "prestasi.html",
        "galeri.html",
        "kegiatan.html",
        "ekstrakurikuler.html"
    ];

    // ambil menu utama navbar
    const navLinks =
        document.querySelectorAll(".nav-menu > li > a");

    navLinks.forEach((link) => {

        // reset semua
        link.classList.remove("active");

        const href =
            link.getAttribute("href");

        if (!href) return;

        // ambil nama file saja
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

/* =========================
   STICKY HEADER
========================= */

function initStickyHeader() {

    const header =
        document.querySelector(".header");

    if (!header) return;

    window.addEventListener("scroll", () => {

        if (window.scrollY > 20) {

            header.classList.add("scrolled");

        } else {

            header.classList.remove("scrolled");
        }
    });
}

/* =========================
   MOBILE MENU
========================= */

function initMobileMenu() {

    const toggle =
        document.querySelector("[data-nav-toggle]");

    const menu =
        document.querySelector("[data-nav-menu]");

    if (!toggle || !menu) return;

    toggle.addEventListener("click", () => {

        const isOpen =
            menu.classList.toggle("navbar__menu--open");

        menu.classList.toggle("active", isOpen);

        toggle.classList.toggle("active", isOpen);

        toggle.setAttribute("aria-expanded", String(isOpen));
    });
}

/* =========================
   INIT
========================= */

function initNavbar() {

    setActiveMenu();

    initStickyHeader();

    initMobileMenu();
}

loadNavbar();
