// CMS Connector - Safe integration for public frontend
// Fetches data from admin database without changing visual design

class CMSConnector {
    constructor(baseUrl = 'api/') {
        this.baseUrl = baseUrl;
        this.newsFallbackImage = 'assets/img/placeholder-news.jpg';

        if (!this.baseUrl.endsWith('/')) {
            this.baseUrl += '/';
        }
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
            console.warn('CMS fetch error:', error);
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

        const existingCards = Array.from(grid.querySelectorAll('.news-card'));
        const shouldPreserveFallbackCards = existingCards.length > news.length;

        if (!shouldPreserveFallbackCards) {
            grid.innerHTML = '';
        }

        news.forEach((item, index) => {
            const card = shouldPreserveFallbackCards
                ? existingCards[index]
                : document.createElement('article');
            const isSmallCard = card.classList.contains('small');
            card.className = isSmallCard ? 'news-card small' : 'news-card';

            let detailUrl;
            if (item.slug && item.slug.trim()) {
                detailUrl = `berita-detail.php?slug=${encodeURIComponent(item.slug)}`;
            } else {
                detailUrl = `berita-detail.php?id=${encodeURIComponent(item.id)}`;
            }

            const thumbSrc = item.thumbnail || this.newsFallbackImage;
            const title = this.escapeHtml(item.title || 'Berita Sekolah');
            const excerpt = this.escapeHtml(item.excerpt || 'Informasi terbaru dari SD Cahaya Harapan Bekasi.');

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

            if (!shouldPreserveFallbackCards) {
                grid.appendChild(card);
            }
        });

        grid.querySelectorAll('.news-card').forEach(card => {
            card.setAttribute('data-aos', 'fade-up');
            card.setAttribute('data-aos-duration', '720');
            card.classList.add('aos-animate');
        });

        console.log('[CMS] News rendered:', news.length, 'cards');
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
                <img src="${item.image}" alt="${this.escapeHtml(item.title)}" loading="lazy" onerror="this.src='${this.newsFallbackImage}'">
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
            slideDiv.className = 'hero-slide';
            slideDiv.style.backgroundImage = `url(${slide.background})`;

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
                <img src="${item.image || 'assets/img/placeholder-achievement.jpg'}" alt="${this.escapeHtml(item.title)}" loading="lazy" onerror="this.src='assets/img/placeholder-achievement.jpg'">
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
document.addEventListener('DOMContentLoaded', function() {
    const cms = new CMSConnector();

    // Homepage integrations
    if (document.querySelector('.news-grid-home')) {
        cms.renderNews('.news-section-home', 4, '.news-grid-home');
    }

    if (document.querySelector('.hero-slider')) {
        cms.renderSlider('.hero-section');
    }

    // Page-specific integrations
    if (window.location.pathname.includes('berita.html')) {
        cms.renderNews('.news-modern', 4, '.latest-news');
    }

    cms.enhanceNewsLinks();

    if (window.location.pathname.includes('galeri.html')) {
        cms.renderGallery('.gallery-section', 20);
    }

    if (window.location.pathname.includes('prestasi.html')) {
        cms.renderAchievements('.achievements-section', 12);
    }

    // Announcements on all pages if container exists
    if (document.querySelector('.announcements-list')) {
        cms.renderAnnouncements('.announcements-section', 5);
    }
});
