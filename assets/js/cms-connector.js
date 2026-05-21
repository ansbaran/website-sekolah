// CMS Connector - Safe integration for public frontend
// Fetches data from admin database without changing visual design

class CMSConnector {
constructor(baseUrl = null) {

    this.basePath = this.getBasePath();
    this.baseUrl = baseUrl || `${this.basePath}/api/`;

    if (!this.baseUrl.endsWith('/')) {
        this.baseUrl += '/';
    }
}

getBasePath() {
    const script = document.currentScript || document.querySelector('script[src*="cms-connector.js"]');
    if (!script) {
        return '';
    }

    const scriptUrl = new URL(script.getAttribute('src'), window.location.href);
    const marker = '/assets/js/cms-connector.js';
    const markerIndex = scriptUrl.pathname.indexOf(marker);

    return markerIndex > 0 ? scriptUrl.pathname.slice(0, markerIndex) : '';
}

normalizeLegacyImagePath(path) {
    const legacyMap = {
        'assets/img/berita1.jpeg': 'assets/img/berita/berita1.jpeg',
        'assets/img/berita5.jpeg': 'assets/img/berita/berita5.jpeg',
        'assets/img/sekolah.jpg': 'assets/img/sekolah.jpg',
    };

    return legacyMap[path] || path;
}

resolveImage(path) {
    if (!path) {
        return '';
    }

    const trimmed = String(path).trim().replace(/\\/g, '/');

    if (trimmed.startsWith('http://') || trimmed.startsWith('https://')) {
        return trimmed;
    }

    let cleanPath = trimmed.replace(/^\/+/, '');

    if (this.basePath) {
        const baseSegment = `${this.basePath.replace(/^\/+/, '')}/`;
        if (cleanPath.startsWith(baseSegment)) {
            cleanPath = cleanPath.slice(baseSegment.length);
        }
    }

    const assetIndex = cleanPath.indexOf('assets/');
    if (assetIndex > 0) {
        cleanPath = cleanPath.slice(assetIndex);
    }

    const uploadIndex = cleanPath.indexOf('uploads/');
    if (uploadIndex > 0) {
        cleanPath = cleanPath.slice(uploadIndex);
    }

    cleanPath = this.normalizeLegacyImagePath(cleanPath);

    if (cleanPath.startsWith('assets/') || cleanPath.startsWith('uploads/')) {
        return `${this.basePath}/${cleanPath}`;
    }

    return `${this.basePath}/assets/img/berita/${cleanPath}`;
}

    async fetchData(endpoint, params = {}) {
        try {
            const url = new URL(this.baseUrl + endpoint, window.location.href);
            Object.keys(params).forEach(key => {
                if (params[key] !== undefined && params[key] !== null) {
                    url.searchParams.append(key, params[key]);
                }
            });

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            return data.status === 'success' ? data.data : [];
        } catch (error) {
            return [];
        }
    }

    createNewsCard(item, templateCard = null, options = {}) {
        const card = document.createElement('article');
        const isSmallCard = options.small || templateCard?.classList.contains('small');
        card.className = isSmallCard ? 'news-card small' : 'news-card';

        if (!isSmallCard && templateCard) {
            card.className = templateCard.className;
        }

        let detailUrl;
        if (item.slug && item.slug.trim()) {
            detailUrl = `berita-detail.php?slug=${encodeURIComponent(item.slug)}`;
        } else {
            detailUrl = `berita-detail.php?id=${encodeURIComponent(item.id)}`;
        }

        const thumbSrc = this.resolveImage(
            item.thumbnail ||
            item.featured_image
        );

        const title = this.escapeHtml(item.title || 'Berita Sekolah');
        const excerpt = this.escapeHtml(
            item.excerpt || 'Informasi terbaru dari SD Cahaya Harapan Bekasi.'
        );

        if (item.slug) {
            card.dataset.slug = item.slug;
        }

        if (item.id) {
            card.dataset.id = item.id;
        }

        card.innerHTML = `
            <div class="news-card__image news-image">
                <img loading="lazy" src="${thumbSrc}" alt="${title}">
            </div>
            <div class="news-card__content news-content">
                <span class="news-card__date news-date">${this.formatDate(item.published_at)}</span>
                <h4 class="news-card__title">${title}</h4>
                <p class="news-card__text">${excerpt}</p>
                <a href="${detailUrl}" class="news-card__button" aria-label="Baca berita ${title}">Baca Selengkapnya <span>&rarr;</span></a>
            </div>
        `;

        return card;
    }

    fillNewsGrid(grid, items, limit, options = {}) {
        grid.innerHTML = '';

        items.slice(0, limit).forEach((item, index) => {
            grid.appendChild(this.createNewsCard(item, null, options));
        });

        grid.querySelectorAll('.news-card').forEach(card => {
            card.setAttribute('data-aos', 'fade-up');
            card.setAttribute('data-aos-duration', '720');
            card.classList.add('aos-animate');
        });
    }

    // Render news cards from CMS data.
    async renderNews(containerSelector, limit = 4, gridSelector = null) {
        const container = document.querySelector(containerSelector);
        if (!container) {
            return;
        }

        const news = await this.fetchData('public-news.php', { limit });

        if (news.length === 0) {
            return;
        }

        const grid = (gridSelector ? container.querySelector(gridSelector) : null) ||
            container.querySelector('.news-grid-home') ||
            container.querySelector('.news-grid') ||
            container.querySelector('[class*="news-grid"]');

        if (!grid) {
            return;
        }

        this.fillNewsGrid(grid, news, limit);

    }

    async renderNewsPage(containerSelector, latestLimit = 4, moreLimit = 8) {
        const container = document.querySelector(containerSelector);
        if (!container) {
            return;
        }

        const latestGrid = container.querySelector('.latest-news');
        const moreGrid = container.querySelector('.more-news');
        if (!latestGrid || !moreGrid) {
            return;
        }

        const news = await this.fetchData('public-news.php', { limit: latestLimit + moreLimit });
        if (news.length === 0) {
            this.enhanceNewsLinks();
            return;
        }

        this.fillNewsGrid(latestGrid, news.slice(0, latestLimit), latestLimit);
        this.fillNewsGrid(moreGrid, news.slice(latestLimit), moreLimit, { small: true });
        this.enhanceNewsLinks();
    }

    enhanceNewsLinks() {
        const cards = document.querySelectorAll('.news-card');
        if (!cards.length) {
            return;
        }


        cards.forEach((card, index) => {
            const link = card.querySelector('.news-content a, .news-card__content a');
            const slug = card?.dataset?.slug?.trim();
            const id = card?.dataset?.id?.trim();
            if (!link) {
                return;
            }

            if (slug) {
                link.href = `berita-detail.php?slug=${encodeURIComponent(slug)}`;
                return;
            }

            if (id) {
                link.href = `berita-detail.php?id=${encodeURIComponent(id)}`;
                return;
            }

        });
    }

    normalizeCategory(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, ' ')
            .trim()
            .split(/\s+/)[0] || 'kegiatan';
    }

    applyGalleryFilters(container) {
        const filterGroup = container.querySelector('[data-gallery-filters]');
        if (!filterGroup) return;

        const buttons = filterGroup.querySelectorAll('[data-filter]');

        const syncButtonState = (activeButton) => {
            buttons.forEach((button) => {
                const isActive = button === activeButton;
                button.classList.toggle('active', isActive);
                button.setAttribute('aria-pressed', String(isActive));
                button.classList.toggle('bg-gradient-to-r', isActive);
                button.classList.toggle('from-[#0D0B61]', isActive);
                button.classList.toggle('to-[#478B8D]', isActive);
                button.classList.toggle('border-transparent', isActive);
                button.classList.toggle('text-white', isActive);
                button.classList.toggle('shadow-lg', isActive);
                button.classList.toggle('text-slate-600', !isActive);
                button.classList.toggle('shadow-sm', !isActive);
            });
        };

        const filterItems = (selectedFilter) => {
            container.querySelectorAll('[data-gallery-item]').forEach((item) => {
                const isVisible = selectedFilter === 'semua' || item.dataset.category === selectedFilter;
                item.hidden = !isVisible;
            });
        };

        const activeButton = filterGroup.querySelector('[aria-pressed="true"]') || buttons[0];
        syncButtonState(activeButton);
        filterItems(activeButton?.dataset.filter || 'semua');

        if (filterGroup.dataset.cmsFilterReady === 'true') {
            return;
        }

        filterGroup.dataset.cmsFilterReady = 'true';
        filterGroup.addEventListener('click', (event) => {
            const button = event.target.closest('[data-filter]');
            if (!button) return;

            syncButtonState(button);
            filterItems(button.dataset.filter);
        });
    }

    // Render gallery grid
    async renderGallery(containerSelector, limit = 12) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const gallery = await this.fetchData('public-gallery.php', { limit });

        if (gallery.length === 0) {
            return;
        }

        const grid = container.querySelector('.simple-gallery-grid') || container.querySelector('.gallery-grid');
        if (!grid) return;

        grid.innerHTML = '';

        gallery.forEach(item => {
            const itemDiv = document.createElement('figure');
            const category = this.normalizeCategory(item.category);
            itemDiv.className = 'simple-gallery-card group overflow-hidden rounded-[1.75rem] bg-white shadow-xl shadow-slate-200/70 transition hover:-translate-y-2 hover:shadow-2xl';
            itemDiv.dataset.galleryItem = '';
            itemDiv.dataset.category = category;

            itemDiv.innerHTML = `
                <img src="${this.resolveImage(item.image)}" alt="${this.escapeHtml(item.title)}" loading="lazy" class="aspect-[4/3] w-full object-cover transition duration-700 group-hover:scale-110">
                <figcaption class="p-5">
                    <h3 class="text-sm font-extrabold text-slate-900">${this.escapeHtml(item.title)}</h3>
                    <p class="mt-1 text-xs font-bold text-slate-500">${this.escapeHtml(item.category)}</p>
                </figcaption>
            `;

            grid.appendChild(itemDiv);
        });

        this.applyGalleryFilters(container);
    }

    // Render hero slider
    async renderSlider(containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const slides = await this.fetchData('public-slider.php', { limit: 5 });

        if (slides.length === 0) {
            return;
        }

        const sliderWrapper = container.querySelector('.hero__slider') || container.querySelector('.hero-slider');
        if (!sliderWrapper) return;

        sliderWrapper.innerHTML = '';

        slides.forEach((slide, index) => {
            const slideDiv = document.createElement('div');
            slideDiv.className = `hero__slide${index === 0 ? ' hero__slide--active' : ''}`;
            slideDiv.dataset.slide = '';
            slideDiv.setAttribute('aria-label', this.escapeHtml(slide.title || `Slide ${index + 1}`));
            const bgImage = this.resolveImage(
    slide.background
);

slideDiv.style.backgroundImage = `url(${bgImage})`;

            sliderWrapper.appendChild(slideDiv);
        });

        this.activateHeroSlides(sliderWrapper);
    }

    activateHeroSlides(sliderWrapper) {
        const slides = Array.from(sliderWrapper.querySelectorAll('[data-slide]'));
        const nextBtn = document.querySelector('.hero__nav--next');
        const prevBtn = document.querySelector('.hero__nav--prev');
        const activeClass = 'hero__slide--active';

        if (slides.length <= 1) return;

        let activeIndex = 0;
        const showSlide = (index) => {
            slides.forEach((slide) => slide.classList.remove(activeClass));
            slides[index].classList.add(activeClass);
        };

        const nextSlide = () => {
            activeIndex = (activeIndex + 1) % slides.length;
            showSlide(activeIndex);
        };

        const prevSlide = () => {
            activeIndex = (activeIndex - 1 + slides.length) % slides.length;
            showSlide(activeIndex);
        };

        window.clearInterval(window.__cmsHeroSliderTimer);
        window.__cmsHeroSliderTimer = window.setInterval(nextSlide, 4500);

        nextBtn?.addEventListener('click', nextSlide);
        prevBtn?.addEventListener('click', prevSlide);
    }

    // Render announcements
    async renderAnnouncements(containerSelector, limit = 3) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const announcements = await this.fetchData('public-announcements.php', { limit });

        if (announcements.length === 0) {
            return;
        }

        const list = container.querySelector('.announcements-list');
        if (!list) return;

        list.innerHTML = '';

        announcements.forEach(item => {
            const li = document.createElement('li');
            li.innerHTML = `
                <strong>${this.escapeHtml(item.title)}</strong> - ${this.escapeHtml(item.content)}
                <small>${this.formatDate(item.published_at)}</small>
            `;
            list.appendChild(li);
        });
    }

    // Render achievements
    async renderAchievements(containerSelector, limit = 6) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const grid = container.querySelector('.achievements-grid') || container.querySelector('#achievements-grid');
        if (!grid) return;

        const achievements = await this.fetchData('public-achievements.php', { limit });

        if (achievements.length === 0) {
            grid.innerHTML = '';
            return;
        }

        grid.innerHTML = '';

        achievements.forEach(item => {
            const card = document.createElement('div');
            card.className = 'gallery-card achievement-card';
            const title = this.escapeHtml(item.title || 'Prestasi Siswa');
            const level = this.escapeHtml(item.level || '');
            const description = this.escapeHtml(item.description || '');

            card.innerHTML = `
                <div class="card-image">
                    <img src="${this.resolveImage(item.image)}" alt="${title}" loading="lazy">
                    <div class="card-overlay">
                        <span class="tag">Kabar Prestasi</span>
                    </div>
                </div>
                <div class="card-footer">
                    <h3>${title}</h3>
                    <p>${level}${description ? ' - ' + description : ''}</p>
                </div>
            `;

            grid.appendChild(card);
        });
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) {
            return '';
        }

        return date.toLocaleDateString('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[char]);
    }
}

// Auto-initialize on page load

document.addEventListener('DOMContentLoaded', function () {

    const cms = new CMSConnector();

    // Homepage integrations
    if (document.querySelector('.news-grid-home')) {

        cms.renderNews('.news-section-home', 4, '.news-grid-home')
            .then(() => {
                cms.enhanceNewsLinks();
            });

    }

    // Hero slider
    if (document.querySelector('.hero__slider') || document.querySelector('.hero-slider')) {
        cms.renderSlider('.hero');
    }

    // Berita page
    if (window.location.pathname.includes('berita.html')) {

        cms.renderNewsPage('.news-modern', 4, 8);

    }

    // Gallery page
    if (window.location.pathname.includes('galeri.html')) {
        cms.renderGallery('.gallery-section', 20);
    }

    // Prestasi page
    if (window.location.pathname.includes('prestasi.html')) {
        cms.renderAchievements('.achievements-section', 12);
    }

    // Announcements
    if (document.querySelector('.announcements-list')) {
        cms.renderAnnouncements('.announcements-section', 5);
    }

});
