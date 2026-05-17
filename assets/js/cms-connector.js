// CMS Connector - Safe integration for public frontend
// Fetches data from admin database without changing visual design

class CMSConnector {
constructor(baseUrl = null) {

    this.basePath = this.getBasePath();
    this.baseUrl = baseUrl || `${this.basePath}/api/`;

    // Absolute fallback paths
    this.newsFallbackImage =
        'assets/img/berita/berita1.jpeg';

    this.achievementFallbackImage =
        'assets/img/prestasi/prestasi-utama.png';

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
        return this.resolveImage(this.newsFallbackImage);
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
            console.error('[CMS] Fetch error:', endpoint, error);
            return [];
        }
    }

    // Render news cards while preserving fallback cards when CMS data is incomplete.
    async renderNews(containerSelector, limit = 4, gridSelector = null) {
        const container = document.querySelector(containerSelector);
        if (!container) {
            console.warn('[CMS] Container not found:', containerSelector);
            return;
        }

        const news = await this.fetchData('public-news.php', { limit });

        if (news.length === 0) {
            console.warn('[CMS] No news data returned from API');
            return;
        }

        const grid = (gridSelector ? container.querySelector(gridSelector) : null) ||
            container.querySelector('.news-grid-home') ||
            container.querySelector('.news-grid') ||
            container.querySelector('[class*="news-grid"]');

        if (!grid) {
            console.warn('[CMS] News grid not found in container. Tried selectors: .news-grid-home, .news-grid, [class*="news-grid"]');
            console.log('[CMS] Container classes:', container.className);
            return;
        }

        const existingCards = Array.from(grid.querySelectorAll('.news-card')).map((card) => card.cloneNode(true));
        grid.innerHTML = '';

        // Defensive: ensure we never render more than limit
        const limitedNews = news.slice(0, limit);

        limitedNews.forEach((item, index) => {
            const card = document.createElement('article');
            const isSmallCard = existingCards[index]?.classList.contains('small');
            card.className = isSmallCard ? 'news-card small' : 'news-card';

            let detailUrl;
            if (item.slug && item.slug.trim()) {
                detailUrl = `berita-detail.php?slug=${encodeURIComponent(item.slug)}`;
            } else {
                detailUrl = `berita-detail.php?id=${encodeURIComponent(item.id)}`;
            }

const thumbSrc = this.resolveImage(
    item.thumbnail ||
    item.featured_image ||
    this.newsFallbackImage
);

const title = this.escapeHtml(item.title || 'Berita Sekolah');

const excerpt = this.escapeHtml(
    item.excerpt || 'Informasi terbaru dari SD Cahaya Harapan Bekasi.'
);

// Simpan slug & id ke dataset
if (item.slug) {
    card.dataset.slug = item.slug;
}

if (item.id) {
    card.dataset.id = item.id;
}
            card.innerHTML = `
                <div class="news-card__image news-image">
                    <img loading="lazy" src="${thumbSrc}" alt="${title}" onerror="this.src='${this.newsFallbackImage}'">
                </div>
                <div class="news-card__content news-content">
                    <span class="news-card__date news-date">${this.formatDate(item.published_at)}</span>
                    <h4 class="news-card__title">${title}</h4>
                    <p class="news-card__text">${excerpt}</p>
                    <a href="${detailUrl}" class="news-card__button" aria-label="Baca berita ${title}">Baca Selengkapnya <span>&rarr;</span></a>
                </div>
            `;

            grid.appendChild(card);
        });

        const currentCount = grid.querySelectorAll('.news-card').length;
        const fallbackNeeded = Math.max(0, limit - currentCount);

        existingCards.slice(0, fallbackNeeded).forEach((fallbackCard) => {
            fallbackCard.removeAttribute('data-slug');
            fallbackCard.removeAttribute('data-id');
            grid.appendChild(fallbackCard);
        });

        const filledCount = grid.querySelectorAll('.news-card').length;
        if (filledCount < limit) {
            for (let i = filledCount; i < limit; i += 1) {
                const placeholder = document.createElement('article');
                placeholder.className = 'news-card';
                placeholder.innerHTML = `
                    <div class="news-card__image news-image">
                        <img loading="lazy" src="${this.newsFallbackImage}" alt="Berita segera hadir" onerror="this.src='${this.newsFallbackImage}'">
                    </div>
                    <div class="news-card__content news-content">
                        <span class="news-card__date news-date">Segera hadir</span>
                        <h4 class="news-card__title">Konten baru segera hadir</h4>
                        <p class="news-card__text">Berita lebih lengkap akan tersedia dalam waktu dekat.</p>
                        <a href="berita.html" class="news-card__button">Lihat Berita <span>&rarr;</span></a>
                    </div>
                `;
                grid.appendChild(placeholder);
            }
        }

        grid.querySelectorAll('.news-card').forEach(card => {
            card.setAttribute('data-aos', 'fade-up');
            card.setAttribute('data-aos-duration', '720');
            card.classList.add('aos-animate');
        });

        console.log('[CMS] News rendered:', grid.querySelectorAll('.news-card').length, 'cards');
    }

    enhanceNewsLinks() {
        const cards = document.querySelectorAll('.news-card');
        if (!cards.length) {
            return;
        }

        console.log(`[CMS] News links initialized - enhancing ${cards.length} cards`);

        cards.forEach((card, index) => {
            const link = card.querySelector('.news-content a, .news-card__content a');
            const slug = card?.dataset?.slug?.trim();
            const id = card?.dataset?.id?.trim();
            if (!link) {
                return;
            }

            if (slug) {
                link.href = `berita-detail.php?slug=${encodeURIComponent(slug)}`;
                console.log('[CMS] Linked slug:', slug);
                return;
            }

            if (id) {
                link.href = `berita-detail.php?id=${encodeURIComponent(id)}`;
                console.log('[CMS] Fallback to ID:', id);
                return;
            }

            console.log('[CMS] Missing slug/id for news card', index + 1);
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

        const grid = container.querySelector('.gallery-grid');
        if (!grid) return;

        grid.innerHTML = '';

        gallery.forEach(item => {
            const itemDiv = document.createElement('div');
            itemDiv.className = 'gallery-item';

            itemDiv.innerHTML = `
                <img src="${this.resolveImage(item.image)}" alt="${this.escapeHtml(item.title)}" loading="lazy" onerror="this.src='${this.newsFallbackImage}'">
                <div class="gallery-overlay">
                    <h4>${this.escapeHtml(item.title)}</h4>
                    <span>${this.escapeHtml(item.category)}</span>
                </div>
            `;

            grid.appendChild(itemDiv);
        });
    }

    // Render hero slider
    async renderSlider(containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const slides = await this.fetchData('public-slider.php', { limit: 5 });

        if (slides.length === 0) {
            return;
        }

        const sliderWrapper = container.querySelector('.hero-slider');
        if (!sliderWrapper) return;

        sliderWrapper.innerHTML = '';

        slides.forEach((slide) => {
            const slideDiv = document.createElement('div');
            const bgImage = this.resolveImage(
    slide.background || this.newsFallbackImage
);

slideDiv.style.backgroundImage = `url(${bgImage})`;
            

            slideDiv.innerHTML = `
                <div class="hero-content">
                    <h1>${this.escapeHtml(slide.title)}</h1>
                    ${slide.subtitle ? `<p>${this.escapeHtml(slide.subtitle)}</p>` : ''}
                    <a href="#ppdb" class="btn-primary">Daftar PPDB</a>
                </div>
            `;

            sliderWrapper.appendChild(slideDiv);
        });

        if (typeof initSlider === 'function') {
            initSlider();
        }
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

        const achievements = await this.fetchData('public-achievements.php', { limit });

        if (achievements.length === 0) {
            return;
        }

        const grid = container.querySelector('.achievements-grid');
        if (!grid) return;

        grid.innerHTML = '';

        achievements.forEach(item => {
            const card = document.createElement('div');
            card.className = 'achievement-card';

            card.innerHTML = `
                <img src="${this.resolveImage(item.image || this.achievementFallbackImage)}" alt="${this.escapeHtml(item.title)}" loading="lazy" onerror="this.src='${this.achievementFallbackImage}'">
                <h4>${this.escapeHtml(item.title)}</h4>
                <span>${this.escapeHtml(item.level)}</span>
                <p>${this.escapeHtml(item.description || '')}</p>
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
    if (document.querySelector('.hero-slider')) {
        cms.renderSlider('.hero-section');
    }

    // Berita page
    if (window.location.pathname.includes('berita.html')) {

        cms.renderNews('.news-modern', 4, '.latest-news')
            .then(() => {
                cms.enhanceNewsLinks();
            });

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
