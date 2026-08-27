const siteRootUrl = new URL("../../", import.meta.url);

function getRouteFromUrl(value = window.location.href) {
    const url = new URL(value, siteRootUrl);
    const basePath = siteRootUrl.pathname.replace(/\/$/, "");
    let pathname = url.pathname;

    if (basePath && pathname === basePath) {
        pathname = "";
    } else if (basePath && pathname.startsWith(`${basePath}/`)) {
        pathname = pathname.slice(basePath.length + 1);
    } else {
        pathname = pathname.replace(/^\/+/, "");
    }

    return pathname.split("/").filter(Boolean)[0] || "";
}

function normalizeNavbarLinks(container) {
    container.querySelectorAll("a[href]").forEach((link) => {
        const href = link.getAttribute("href");

        if (
            !href ||
            /^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i.test(href)
        ) {
            return;
        }

        link.href = new URL(href, siteRootUrl).href;
    });
}

async function loadNavbar() {
    const navbarContainer = document.getElementById("navbar");
    if (!navbarContainer) return;

    const candidatePaths = [
        new URL("components/navbar.html", siteRootUrl).href,
        new URL("components/navbar.html", window.location.origin + "/").href
    ];

    for (const componentUrl of candidatePaths) {
        try {
            const response = await fetch(componentUrl, { cache: "no-cache" });

            if (!response.ok) {
                continue;
            }

            const html = await response.text();
            navbarContainer.innerHTML = html;
            normalizeNavbarLinks(navbarContainer);
            initNavbar();
            document.dispatchEvent(new CustomEvent("navbar:loaded"));
            return;
        } catch (error) {
            continue;
        }
    }
}

let navbarInitialized = false;

function getDropdownToggleLabel(toggle) {
    return (toggle?.textContent || "").replace(/\s+/g, " ").trim();
}

/* =========================
   ACTIVE MENU
========================= */

function setActiveMenu() {
    const currentRoute = getRouteFromUrl();
    const activityRoutes = new Set([
        "achievements",
        "gallery",
        "activity",
        "agenda",
        "extracurricular"
    ]);

    const navItems = document.querySelectorAll(
        ".nav-menu > li > a, .nav-menu > li > .dropdown-toggle"
    );

    navItems.forEach((item) => {
        const href = item.getAttribute("href");
        const linkRoute = href ? getRouteFromUrl(href) : null;
        const label = getDropdownToggleLabel(item);

        const isActive =
            (label.startsWith("Tentang") && currentRoute === "about") ||
            (label.startsWith("Aktivitas") && activityRoutes.has(currentRoute)) ||
            (
                linkRoute !== null &&
                linkRoute === currentRoute &&
                !label.startsWith("Tentang") &&
                !label.startsWith("Aktivitas")
            );

        item.classList.toggle("active", isActive);
    });
}

/* =========================
   STICKY HEADER
========================= */

function initStickyHeader() {
    const header = document.querySelector(".header");
    if (!header) return;

    const syncHeaderState = () => {
        header.classList.toggle("scrolled", window.scrollY > 20);
    };

    syncHeaderState();
    window.addEventListener("scroll", syncHeaderState, { passive: true });
}

function getFocusableElements(scope) {
    return Array.from(scope.querySelectorAll("a[href], button:not([disabled]), [tabindex]:not([tabindex='-1'])"))
        .filter((element) => element instanceof HTMLElement && element.getClientRects().length > 0);
}

function setDropdownState(dropdown, isOpen) {
    const toggle = dropdown.querySelector(".dropdown-toggle");
    dropdown.classList.toggle("open", isOpen);
    toggle?.setAttribute("aria-expanded", String(isOpen));
}

function closeAllDropdowns({ returnFocus = false } = {}) {
    const openToggle = document.querySelector(".dropdown.open > .dropdown-toggle");

    document.querySelectorAll(".dropdown.open").forEach((dropdown) => {
        setDropdownState(dropdown, false);
    });

    if (returnFocus && openToggle instanceof HTMLElement) {
        openToggle.focus({ preventScroll: true });
    }
}

/* =========================
   MOBILE MENU
========================= */

function initMobileMenu() {
    const toggle = document.querySelector("[data-nav-toggle]");
    const menu = document.querySelector("[data-nav-menu]");

    if (!toggle || !menu) return;

    let previousFocus = null;

    const isMenuOpen = () => menu.classList.contains("navbar__menu--open");

    const closeMenu = ({ restoreFocus = true } = {}) => {
        if (!isMenuOpen()) return;

        menu.classList.remove("navbar__menu--open");
        menu.classList.remove("active");
        toggle.classList.remove("active");
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Buka menu navigasi");
        document.body.classList.remove("nav-menu-open");
        closeAllDropdowns();

        if (restoreFocus && previousFocus instanceof HTMLElement) {
            previousFocus.focus({ preventScroll: true });
        }

        previousFocus = null;
    };

    const openMenu = () => {
        if (isMenuOpen()) return;

        previousFocus = document.activeElement instanceof HTMLElement ? document.activeElement : toggle;
        menu.classList.add("navbar__menu--open");
        menu.classList.add("active");
        toggle.classList.add("active");
        toggle.setAttribute("aria-expanded", "true");
        toggle.setAttribute("aria-label", "Tutup menu navigasi");
        document.body.classList.add("nav-menu-open");

        const firstFocusable = getFocusableElements(menu)[0];
        firstFocusable?.focus({ preventScroll: true });
    };

    toggle.addEventListener("click", () => {
        if (isMenuOpen()) {
            closeMenu();
            return;
        }

        openMenu();
    });

    menu.addEventListener("click", (event) => {
        const link = event.target.closest("a[href]");
        if (!link) return;

        closeMenu({ restoreFocus: false });
    });

    document.addEventListener("click", (event) => {
        if (event.target.closest("[data-nav-menu]") || event.target.closest("[data-nav-toggle]")) {
            return;
        }

        closeMenu({ restoreFocus: false });
    });

    document.addEventListener("keydown", (event) => {
        if (!isMenuOpen()) return;

        if (event.key === "Escape") {
            event.preventDefault();
            closeMenu();
            return;
        }

        if (event.key !== "Tab") return;

        const focusable = getFocusableElements(menu);
        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus({ preventScroll: true });
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus({ preventScroll: true });
        }
    });
}

/* =========================
   DROPDOWN MENU
========================= */

function initDropdownMenus() {
    const dropdowns = Array.from(document.querySelectorAll(".dropdown"));
    const desktopQuery = window.matchMedia("(min-width: 1024px)");

    if (dropdowns.length === 0) return;

    const closeSiblingDropdowns = (activeDropdown) => {
        dropdowns.forEach((dropdown) => {
            if (dropdown === activeDropdown) return;
            setDropdownState(dropdown, false);
        });
    };

    const openDropdown = (dropdown) => {
        closeSiblingDropdowns(dropdown);
        setDropdownState(dropdown, true);
    };

    const toggleDropdown = (dropdown) => {
        const willOpen = !dropdown.classList.contains("open");
        closeSiblingDropdowns(dropdown);
        setDropdownState(dropdown, willOpen);
    };

    dropdowns.forEach((dropdown) => {
        const toggle = dropdown.querySelector(".dropdown-toggle");
        const menu = dropdown.querySelector(".dropdown-menu");

        if (!toggle || !menu) return;

        toggle.setAttribute("aria-haspopup", "true");
        toggle.setAttribute("aria-expanded", dropdown.classList.contains("open") ? "true" : "false");

        toggle.addEventListener("click", (event) => {
            event.preventDefault();
            event.stopPropagation();
            toggleDropdown(dropdown);
        });

        toggle.addEventListener("keydown", (event) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                toggleDropdown(dropdown);
                return;
            }

            if (event.key === "ArrowDown") {
                event.preventDefault();
                openDropdown(dropdown);
                const firstLink = menu.querySelector("a[href]");
                firstLink?.focus({ preventScroll: true });
                return;
            }

            if (event.key === "Escape") {
                event.preventDefault();
                closeAllDropdowns({ returnFocus: true });
            }
        });

        dropdown.addEventListener("mouseenter", () => {
            if (!desktopQuery.matches) return;
            openDropdown(dropdown);
        });

        dropdown.addEventListener("mouseleave", () => {
            if (!desktopQuery.matches) return;
            setDropdownState(dropdown, false);
        });

        dropdown.addEventListener("focusin", () => {
            if (!desktopQuery.matches) return;
            openDropdown(dropdown);
        });
    });

    document.addEventListener("click", (event) => {
        if (event.target.closest(".dropdown")) return;
        closeAllDropdowns();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            closeAllDropdowns({ returnFocus: true });
        }
    });

    window.addEventListener("scroll", () => {
        closeAllDropdowns();
    }, { passive: true });

    window.addEventListener("resize", () => {
        closeAllDropdowns();
    });
}

/* =========================
   INIT
========================= */

function initNavbar() {
    if (navbarInitialized) return;
    navbarInitialized = true;

    setActiveMenu();
    initStickyHeader();
    initMobileMenu();
    initDropdownMenus();
}

loadNavbar();
