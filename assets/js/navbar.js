async function loadNavbar() {
    const navbarContainer = document.getElementById("navbar");
    if (!navbarContainer) return;

    const pageDirectory = window.location.pathname.replace(/\/[^\/]*$/, '/');
    const rootPath = pageDirectory.includes('/website-sekolah/')
        ? '/website-sekolah/'
        : '/';

    const candidatePaths = [
        new URL('../../components/navbar.html', import.meta.url).href,
        `${window.location.origin}${pageDirectory}components/navbar.html`,
        `${window.location.origin}${rootPath}components/navbar.html`,
        `${window.location.origin}/components/navbar.html`
    ];

    console.log('[Navbar] mencari komponen di path:', candidatePaths);

    for (const componentUrl of candidatePaths) {
        try {
            const response = await fetch(componentUrl, { cache: 'no-cache' });

            if (!response.ok) {
                console.warn('[Navbar] path tidak ditemukan:', componentUrl, response.status);
                continue;
            }

            const html = await response.text();
            navbarContainer.innerHTML = html;
            initNavbar();
            document.dispatchEvent(new CustomEvent('navbar:loaded'));
            console.log('[Navbar] berhasil dimuat dari:', componentUrl);
            return;
        } catch (error) {
            console.warn('[Navbar] gagal memuat path:', componentUrl, error);
        }
    }

    console.error('Navbar gagal dimuat: komponen navbar tidak ditemukan pada semua path fallback');
}

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
   DROPDOWN MENU
========================= */

function initDropdownMenus() {
    const dropdowns = document.querySelectorAll(".dropdown");
    const desktopQuery = window.matchMedia("(min-width: 1024px)");

    dropdowns.forEach((dropdown) => {
        const toggle = dropdown.querySelector(".dropdown-toggle");
        const menu = dropdown.querySelector(".dropdown-menu");

        if (!toggle || !menu) return;

        toggle.setAttribute("aria-haspopup", "true");
        toggle.setAttribute("aria-expanded", dropdown.classList.contains("open") ? "true" : "false");

        toggle.addEventListener("click", (event) => {
            if (desktopQuery.matches) return;

            event.preventDefault();

            const isOpen = dropdown.classList.toggle("open");
            toggle.setAttribute("aria-expanded", String(isOpen));

            dropdowns.forEach((otherDropdown) => {
                if (otherDropdown === dropdown) return;
                otherDropdown.classList.remove("open");
                otherDropdown.querySelector(".dropdown-toggle")?.setAttribute("aria-expanded", "false");
            });
        });

        dropdown.addEventListener("mouseenter", () => {
            if (!desktopQuery.matches) return;
            dropdown.classList.add("open");
            toggle.setAttribute("aria-expanded", "true");
        });

        dropdown.addEventListener("mouseleave", () => {
            if (!desktopQuery.matches) return;
            dropdown.classList.remove("open");
            toggle.setAttribute("aria-expanded", "false");
        });
    });

    document.addEventListener("click", (event) => {
        if (desktopQuery.matches) return;
        if (event.target.closest(".dropdown")) return;

        dropdowns.forEach((dropdown) => {
            dropdown.classList.remove("open");
            dropdown.querySelector(".dropdown-toggle")?.setAttribute("aria-expanded", "false");
        });
    });
}

/* =========================
   INIT
========================= */

function initNavbar() {

    setActiveMenu();

    initStickyHeader();

    initMobileMenu();

    initDropdownMenus();
}

loadNavbar();
