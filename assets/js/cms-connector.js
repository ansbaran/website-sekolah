// CMS Connector - Safe integration for public frontend
// Fetches data from admin database without changing visual design

class CMSConnector {
    constructor(baseUrl = '/api/') {
        this.baseUrl = baseUrl;
    }

    async fetchData(endpoint, params = {}) {
        try {
            const url = new URL(this.baseUrl + endpoint, window.location.origin);
            Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));

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

    // Render news cards without changing existing structure
    async renderNews(containerSelector, limit = 4) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const news = await this.fetchData('public-news.php', { limit });

        if (news.length === 0) {
            // Fallback: keep existing placeholder or add safe placeholder
            return;
        }

        // Clear existing content but keep structure
        const grid = container.querySelector('.news-grid-home');
        if (!grid) return;

        grid.innerHTML = '';

        news.forEach(item => {
            const card = document.createElement('article');
            card.className = 'news-card';

            card.innerHTML = `
                <div class="news-image">
                    <img loading="lazy" src="${item.thumbnail || '/assets/img/placeholder-news.jpg'}" alt="${item.title}" onerror="this.src='/assets/img/placeholder-news.jpg'">
                </div>
                <div class="news-content">
                    <span class="news-date">${this.formatDate(item.published_at)}</span>
                    <h4>${item.title}</h4>
                    <p>${item.excerpt || 'Deskripsi berita...'}</p>
                    <a href="berita.html">Baca Selengkapnya →</a>
                </div>
            `;

            grid.appendChild(card);
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
                <img src="${item.image}" alt="${item.title}" loading="lazy" onerror="this.src='/assets/img/placeholder-news.jpg'">
                <div class="gallery-overlay">
                    <h4>${item.title}</h4>
                    <span>${item.category}</span>
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

        slides.forEach((slide, index) => {
            const slideDiv = document.createElement('div');
            slideDiv.className = 'hero-slide';
            slideDiv.style.backgroundImage = `url(${slide.background})`;

            slideDiv.innerHTML = `
                <div class="hero-content">
                    <h1>${slide.title}</h1>
                    ${slide.subtitle ? `<p>${slide.subtitle}</p>` : ''}
                    <a href="#ppdb" class="btn-primary">Daftar PPDB</a>
                </div>
            `;

            sliderWrapper.appendChild(slideDiv);
        });

        // Reinitialize slider if exists
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
                <strong>${item.title}</strong> - ${item.content}
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
                <img src="${item.image || '/assets/img/placeholder-achievement.jpg'}" alt="${item.title}" loading="lazy" onerror="this.src='/assets/img/placeholder-achievement.jpg'">
                <h4>${item.title}</h4>
                <span>${item.level}</span>
                <p>${item.description || ''}</p>
            `;

            grid.appendChild(card);
        });
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }
}

// Auto-initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    const cms = new CMSConnector();

    // Homepage integrations
    if (document.querySelector('.news-grid-home')) {
        cms.renderNews('.news-section-home', 4);
    }

    if (document.querySelector('.hero-slider')) {
        cms.renderSlider('.hero-section');
    }

    // Page-specific integrations
    if (window.location.pathname.includes('berita.html')) {
        cms.renderNews('.news-section', 12);
    }

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
