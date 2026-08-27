// CMS Connector - Safe integration for public frontend
// Fetches data from admin database without changing visual design

class CMSConnector {
constructor(baseUrl = null) {

    this.basePath = this.getBasePath();
    this.baseUrl = baseUrl || `${this.basePath}/api/`;

    this.fallbackSlides = [
        'assets/img/sekolah.jpg',
        'assets/img/sekolah2.jpg',
        'assets/img/sekolah3.jpg'
    ];

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

getCurrentRoute() {
    let pathname = window.location.pathname;

    if (this.basePath && pathname === this.basePath) {
        return '';
    }

    if (
        this.basePath &&
        pathname.startsWith(`${this.basePath}/`)
    ) {
        pathname = pathname.slice(this.basePath.length + 1);
    } else {
        pathname = pathname.replace(/^\/+/, '');
    }

    return pathname.split('/').filter(Boolean)[0] || '';
}

resolvePublicUrl(path = '') {
    const cleanPath = String(path || '').replace(/^\/+/, '');
    return `${this.basePath}/${cleanPath}`;
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

    async fetchData(endpoint, params = {}, options = {}) {
        const controller = 'AbortController' in window ? new AbortController() : null;
        const timeoutMs = Number.isFinite(options.timeoutMs) ? options.timeoutMs : 4500;
        const timeout = controller && timeoutMs > 0 ? window.setTimeout(() => controller.abort(), timeoutMs) : null;

        try {
            const url = new URL(this.baseUrl + endpoint, window.location.href);
            Object.keys(params).forEach(key => {
                if (params[key] !== undefined && params[key] !== null) {
                    url.searchParams.append(key, params[key]);
                }
            });

            const response = await fetch(url, {
                method: 'GET',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                },
                signal: controller?.signal
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            return data.status === 'success' ? data.data : [];
        } catch (error) {
            return [];
        } finally {
            if (timeout) {
                window.clearTimeout(timeout);
            }
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
            detailUrl = this.resolvePublicUrl(`news/${encodeURIComponent(item.slug)}`);
        } else {
            detailUrl = this.resolvePublicUrl(`news/id/${encodeURIComponent(item.id)}`);
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
                <img loading="lazy" decoding="async" width="640" height="420" src="${thumbSrc}" alt="${title}">
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
    createAgendaListCard(item) {
        const date = this.formatShortDateParts(item.event_date);
        const detailUrl = item.slug
            ? this.resolvePublicUrl(`agenda/${encodeURIComponent(item.slug)}`)
            : this.resolvePublicUrl('#info-sekolah');
        const card = document.createElement('article');
        card.className = 'agenda-list-card';
        card.innerHTML = `
            <a class="agenda-list-card__link" href="${detailUrl}">
                <time class="agenda-list-card__date" datetime="${this.escapeHtml(item.event_date || '')}">
                    <span>${this.escapeHtml(date.month)}</span>
                    <strong>${this.escapeHtml(date.day)}</strong>
                    <small>${this.escapeHtml(date.full || '')}</small>
                </time>
                <div class="agenda-list-card__body">
                    <span class="agenda-list-card__badge"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i> Agenda</span>
                    <h3>${this.escapeHtml(item.title || 'Agenda Sekolah')}</h3>
                    <p>${this.escapeHtml(this.truncateText(item.summary || 'Informasi agenda sekolah akan diperbarui secara berkala.', 132))}</p>
                    <div class="agenda-list-card__meta">
                        <span><i class="fa-regular fa-clock" aria-hidden="true"></i> ${this.escapeHtml(item.event_time || '-')}</span>
                        <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> ${this.escapeHtml(item.location || 'Sekolah')}</span>
                    </div>
                </div>
                <span class="agenda-list-card__arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
            </a>
        `;
        return card;
    }

    async renderAgendaPage(containerSelector = '[data-agenda-page]', limit = 12) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const grid = container.querySelector('[data-agenda-grid]');
        const count = container.querySelector('[data-agenda-count]');
        if (!grid) return;

        const agendas = await this.fetchData('public-agendas.php', { limit });
        grid.replaceChildren();

        if (count) {
            count.textContent = `${agendas.length} agenda tersedia`;
        }

        if (!agendas.length) {
            grid.innerHTML = '<div class="agenda-list-empty">Belum ada agenda aktif yang dipublikasikan.</div>';
            return;
        }

        agendas.forEach((item) => {
            grid.appendChild(this.createAgendaListCard(item));
        });
    }
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
                link.href = this.resolvePublicUrl(`news/${encodeURIComponent(slug)}`);
                return;
            }

            if (id) {
                link.href = this.resolvePublicUrl(`news/id/${encodeURIComponent(id)}`);
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

    applyGalleryFilters(container, onChange = null) {
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
            if (typeof onChange === 'function') {
                onChange(button.dataset.filter);
            } else {
                filterItems(button.dataset.filter);
            }
        });
    }

    // Render gallery grid
    async renderGallery(containerSelector, limit = 12, category = 'semua') {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const grid = container.querySelector('.simple-gallery-grid') || container.querySelector('.gallery-grid');
        if (!grid) return;

        const loading = container.querySelector('[data-gallery-loading]');
        const empty = container.querySelector('[data-gallery-empty]');
        loading?.classList.remove('hidden');
        loading?.classList.add('flex');
        empty?.classList.add('hidden');
        grid.classList.add('is-loading');

        const params = { limit };
        if (category && category !== 'semua') {
            params.category = category;
        }
        const gallery = await this.fetchData('public-gallery.php', params);

        grid.innerHTML = '';

        gallery.forEach(item => {
            const itemDiv = document.createElement('figure');
            const category = this.normalizeCategory(item.category);
            itemDiv.className = 'simple-gallery-card gallery-card-fade group overflow-hidden rounded-[1.75rem] bg-white shadow-xl shadow-slate-200/70 transition hover:-translate-y-2 hover:shadow-2xl';
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

        loading?.classList.add('hidden');
        loading?.classList.remove('flex');
        grid.classList.remove('is-loading');
        empty?.classList.toggle('hidden', gallery.length > 0);

        this.applyGalleryFilters(container, (selectedCategory) => {
            this.renderGallery(containerSelector, limit, selectedCategory);
        });
    }

    // Render hero slider
    async renderSlider(containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const slides = await this.fetchData('public-slider.php', { limit: 5 }, { timeoutMs: 15000 });

        const sliderWrapper = container.querySelector('.hero__slider') || container.querySelector('.hero-slider');
        if (!sliderWrapper) return;

        sliderWrapper.innerHTML = '';

        const heroSlides = slides.length > 0
            ? slides
            : this.fallbackSlides.map((background, index) => ({
                title: `SD Cahaya Harapan Bekasi ${index + 1}`,
                background
            }));

        heroSlides.forEach((slide, index) => {
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

        const list = container.querySelector('.announcements-list');
        if (!list) return;

        const announcements = await this.fetchData('public-announcements.php', { limit });
        list.replaceChildren();

        if (announcements.length === 0) {
            container.hidden = true;
            return;
        }

        container.hidden = false;

        announcements.slice(0, limit).forEach((item, index) => {
            const card = document.createElement('article');
            card.className = 'group rounded-[1.5rem] border border-blue-100/80 bg-white p-5 shadow-[0_18px_45px_rgba(37,99,235,0.08)] transition duration-300 hover:-translate-y-1 hover:border-blue-200 hover:shadow-[0_24px_55px_rgba(37,99,235,0.14)]';

            const meta = document.createElement('div');
            meta.className = 'mb-4 flex items-center justify-between gap-3';

            const badge = document.createElement('span');
            badge.className = 'rounded-full bg-blue-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-blue-700';
            badge.textContent = index === 0 ? 'Baru' : 'Info';

            const date = document.createElement('time');
            date.className = 'text-xs font-semibold text-slate-400';
            date.dateTime = item.published_at || '';
            date.textContent = this.formatDate(item.published_at);

            const title = document.createElement('h3');
            title.className = 'text-lg font-black leading-snug text-slate-950';
            title.textContent = item.title || 'Pengumuman Sekolah';

            const content = document.createElement('p');
            content.className = 'mt-3 line-clamp-3 text-sm leading-6 text-slate-600';
            content.textContent = item.content || '';

            meta.appendChild(badge);
            meta.appendChild(date);
            card.appendChild(meta);
            card.appendChild(title);
            card.appendChild(content);
            list.appendChild(card);
        });
    }


    formatShortDateParts(dateString) {
        const date = new Date(dateString || '');
        if (Number.isNaN(date.getTime())) {
            return { day: '--', month: 'Info', full: '' };
        }

        return {
            day: date.toLocaleDateString('id-ID', { day: '2-digit' }),
            month: date.toLocaleDateString('id-ID', { month: 'short' }).replace('.', ''),
            full: this.formatDate(dateString)
        };
    }

    truncateText(value, maxLength = 96) {
        const text = this.decodeHtml ? this.decodeHtml(value || '') : String(value || '');
        if (text.length <= maxLength) return text;
        return `${text.slice(0, maxLength).trim()}...`;
    }

    createSchoolInfoAgenda(item) {
        const date = this.formatShortDateParts(item.event_date);
        const detailUrl = item.slug
            ? this.resolvePublicUrl(`agenda/${encodeURIComponent(item.slug)}`)
            : this.resolvePublicUrl('#info-sekolah');
        const card = document.createElement('a');
        card.className = 'school-info-item school-info-item--agenda';
        card.href = detailUrl;
        card.innerHTML = `
            <time class="school-info-datebox" datetime="${this.escapeHtml(item.event_date || '')}">
                <strong>${this.escapeHtml(date.day)}</strong>
                <span>${this.escapeHtml(date.month)}</span>
            </time>
            <div class="school-info-item__body">
                <h4>${this.escapeHtml(item.title || 'Agenda Sekolah')}</h4>
                <p><i class="fa-regular fa-clock" aria-hidden="true"></i> ${this.escapeHtml(item.event_time || '-')} <span aria-hidden="true">&bull;</span> ${this.escapeHtml(item.location || 'Sekolah')}</p>
            </div>
        `;
        return card;
    }
    createSchoolInfoAnnouncement(item, index = 0) {
        const date = this.formatShortDateParts(item.published_at);
        const link = this.resolvePublicUrl(`announcements/${encodeURIComponent(item.id || index + 1)}`);
        const card = document.createElement('a');
        card.className = 'school-info-item school-info-item--announcement';
        card.href = link;
        card.innerHTML = `
            <time class="school-info-datebox" datetime="${this.escapeHtml(item.published_at || '')}">
                <strong>${this.escapeHtml(date.day)}</strong>
                <span>${this.escapeHtml(date.month)}</span>
            </time>
            <div class="school-info-item__body">
                <span class="school-info-badge ${index === 0 ? 'school-info-badge--new' : ''}">${index === 0 ? 'Baru' : 'Pengumuman'}</span>
                <h4>${this.escapeHtml(item.title || 'Pengumuman Sekolah')}</h4>
                <p>${this.escapeHtml(this.truncateText(item.content || '', 92))}</p>
            </div>
        `;
        return card;
    }

    createSchoolInfoArticle(item) {
        const title = this.escapeHtml(item.title || 'Artikel Sekolah');
        const image = this.resolveImage(item.thumbnail || item.featured_image || item.image || 'assets/img/sekolah.jpg');
        const detailUrl = item.slug
            ? this.resolvePublicUrl(`news/${encodeURIComponent(item.slug)}`)
            : this.resolvePublicUrl(`news/id/${encodeURIComponent(item.id || '')}`);
        const card = document.createElement('a');
        card.className = 'school-info-item school-info-item--article';
        card.href = detailUrl;
        card.innerHTML = `
            <span class="school-info-thumb" aria-hidden="true">
                <img src="${image}" alt="" loading="lazy" decoding="async" width="96" height="96">
            </span>
            <span class="school-info-item__body">
                <h4>${title}</h4>
                <span class="school-info-meta">${this.escapeHtml(this.formatDate(item.published_at))} <b aria-hidden="true">&bull;</b> Berita</span>
            </span>
        `;
        return card;
    }

    async renderSchoolInfoSection(containerSelector = '[data-school-info]') {
        const container = document.querySelector(containerSelector);
        if (!container) return;

        const agendaList = container.querySelector('[data-agenda-list]');
        const announcementsList = container.querySelector('[data-school-announcements-list]');
        const articlesList = container.querySelector('[data-school-articles-list]');

        if (agendaList) {
            const agendas = await this.fetchData('public-agendas.php', { limit: 3 });
            agendaList.replaceChildren();
            if (agendas.length) {
                agendas.slice(0, 3).forEach((item) => {
                    agendaList.appendChild(this.createSchoolInfoAgenda(item));
                });
            } else {
                agendaList.innerHTML = '<div class="school-info-empty">Belum ada agenda terbaru.</div>';
            }
        }

        if (announcementsList) {
            const announcements = await this.fetchData('public-announcements.php', { limit: 3 });
            announcementsList.replaceChildren();
            if (announcements.length) {
                announcements.slice(0, 3).forEach((item, index) => {
                    announcementsList.appendChild(this.createSchoolInfoAnnouncement(item, index));
                });
            } else {
                announcementsList.innerHTML = '<div class="school-info-empty">Belum ada pengumuman terbaru.</div>';
            }
        }

        if (articlesList) {
            const articles = await this.fetchData('public-news.php', { limit: 4 });
            articlesList.replaceChildren();
            if (articles.length) {
                articles.slice(0, 4).forEach((item) => {
                    articlesList.appendChild(this.createSchoolInfoArticle(item));
                });
            } else {
                articlesList.innerHTML = '<div class="school-info-empty">Belum ada artikel terbaru.</div>';
            }
        }
    }
    getAchievementFallbackItems() {
        return [
            {
                title: 'Prestasi Lainnya Akan Segera Diperbarui',
                level: 'Ruang Prestasi Sekolah',
                description: 'Data prestasi terbaru akan tampil otomatis di sini setelah diperbarui.',
                image: 'assets/img/prestasi/prestasi-utama.png',
                badge: 'Update',
                category: 'Info'
            }
        ];
    }

    decodeHtml(value) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = String(value || '');
        return textarea.value;
    }

    normalizeAchievementShowcaseItem(item, index = 0) {
        const title = this.decodeHtml(item.title || 'Prestasi Siswa');
        const level = this.decodeHtml(item.level || 'Prestasi Sekolah');
        const description = this.decodeHtml(item.description || 'Pencapaian siswa SD Cahaya Harapan Bekasi di berbagai bidang pengembangan diri.');
        const combined = `${title} ${level} ${description}`.toLowerCase();
        const category = item.category ||
            (combined.includes('renang') || combined.includes('o2sn') || combined.includes('catur') ? 'Olahraga' :
                combined.includes('tari') || combined.includes('fls3n') || combined.includes('seni') ? 'Seni' :
                    combined.includes('osn') || combined.includes('akademik') || combined.includes('sains') ? 'Akademik' : 'Prestasi');
        const badgeMatch = title.match(/juara\s*[0-9ivx]+|harapan\s*[0-9ivx]+|fls3n|osn|o2sn|catur|renang/i);

        return {
            title,
            level,
            description,
            image: this.resolveImage(item.image) || `${this.basePath}/assets/img/prestasi/prestasi-utama.png`,
            badge: item.badge || (badgeMatch ? badgeMatch[0].replace(/\b\w/g, char => char.toUpperCase()) : 'Prestasi'),
            category,
            year: item.year || '',
            index
        };
    }

    createAchievementShowcaseCard(item, index) {
        const card = document.createElement('article');
        card.className = `achievement-showcase__card${index === 0 ? ' is-active' : ''}`;
        card.dataset.achievementCard = '';

        const media = document.createElement('div');
        media.className = 'achievement-showcase__media';

        const image = document.createElement('img');
        image.src = item.image;
        image.alt = item.title;
        image.loading = 'lazy';
        image.decoding = 'async';
        image.addEventListener('error', () => {
            image.src = `${this.basePath}/assets/img/prestasi/prestasi-utama.png`;
        }, { once: true });

        const badge = document.createElement('span');
        badge.className = 'achievement-showcase__badge';
        badge.textContent = item.badge;

        media.appendChild(image);
        media.appendChild(badge);

        const body = document.createElement('div');
        body.className = 'achievement-showcase__body';

        const level = document.createElement('span');
        level.className = 'achievement-showcase__level';
        level.textContent = item.level;

        const title = document.createElement('h3');
        title.textContent = item.title;

        const description = document.createElement('p');
        description.textContent = item.description;

        const meta = document.createElement('div');
        meta.className = 'achievement-showcase__meta';

        [item.category, item.year].filter(Boolean).slice(0, 2).forEach(value => {
            const chip = document.createElement('span');
            chip.textContent = value;
            meta.appendChild(chip);
        });

        body.appendChild(level);
        body.appendChild(title);
        body.appendChild(description);
        body.appendChild(meta);

        card.appendChild(media);
        card.appendChild(body);
        return card;
    }

    async renderAchievementShowcase(containerSelector = '[data-achievement-showcase]', limit = 8) {
        const container = document.querySelector(containerSelector);
        if (!container) {
            return;
        }

        const stage = container.querySelector('[data-achievement-stage]');
        const dots = container.querySelector('[data-achievement-dots]');
        const preview = container.querySelector('[data-achievement-preview]');
        const count = container.querySelector('[data-achievement-count]');
        const nextButton = container.querySelector('[data-achievement-next]');
        const prevButton = container.querySelector('[data-achievement-prev]');

        if (!stage || !dots) {
            return;
        }

        const fetchedItems = await this.fetchData('public-achievements.php', { limit });
        const hasRealItems = fetchedItems.length > 0;
        const sourceItems = hasRealItems ? fetchedItems : this.getAchievementFallbackItems();
        const items = sourceItems
            .slice(0, Math.max(1, Math.min(limit, sourceItems.length)))
            .map((item, index) => this.normalizeAchievementShowcaseItem(item, index));

        if (!items.length) {
            return;
        }

        container.classList.toggle('is-single', items.length <= 1);
        container.classList.toggle('is-empty', !hasRealItems);

        stage.replaceChildren();
        dots.replaceChildren();
        preview?.replaceChildren();

        items.forEach((item, index) => {
            stage.appendChild(this.createAchievementShowcaseCard(item, index));

            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = `achievement-showcase__dot${index === 0 ? ' is-active' : ''}`;
            dot.dataset.achievementDot = String(index);
            dot.setAttribute('aria-label', `Tampilkan prestasi ${index + 1}`);
            dots.appendChild(dot);
        });

        items.slice(0, 3).forEach((item) => {
            if (!preview) {
                return;
            }
            const image = document.createElement('img');
            image.src = item.image;
            image.alt = '';
            image.loading = 'lazy';
            image.decoding = 'async';
            image.addEventListener('error', () => {
                image.src = `${this.basePath}/assets/img/prestasi/prestasi-utama.png`;
            }, { once: true });
            preview.appendChild(image);
        });

        if (count) {
            count.textContent = hasRealItems ? 'Prestasi pilihan sekolah' : 'Prestasi lainnya akan segera diperbarui';
        }

        const cards = Array.from(stage.querySelectorAll('[data-achievement-card]'));
        const dotButtons = Array.from(dots.querySelectorAll('[data-achievement-dot]'));
        let activeIndex = 0;
        let timer = null;
        const canRotate = items.length > 1;
        [nextButton, prevButton].forEach((button) => {
            if (!button) {
                return;
            }
            button.disabled = !canRotate;
            button.setAttribute('aria-disabled', String(!canRotate));
        });

        const show = (index) => {
            activeIndex = (index + items.length) % items.length;
            cards.forEach((card, cardIndex) => {
                card.classList.toggle('is-active', cardIndex === activeIndex);
            });
            dotButtons.forEach((dot, dotIndex) => {
                dot.classList.toggle('is-active', dotIndex === activeIndex);
                dot.setAttribute('aria-current', dotIndex === activeIndex ? 'true' : 'false');
            });
        };

        const stop = () => {
            if (timer) {
                window.clearInterval(timer);
                timer = null;
            }
        };

        const start = () => {
            if (!canRotate || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }
            stop();
            timer = window.setInterval(() => show(activeIndex + 1), 5200);
        };

        nextButton?.addEventListener('click', () => {
            show(activeIndex + 1);
            start();
        });

        prevButton?.addEventListener('click', () => {
            show(activeIndex - 1);
            start();
        });

        dotButtons.forEach((dot, index) => {
            dot.addEventListener('click', () => {
                show(index);
                start();
            });
        });

        container.addEventListener('mouseenter', stop);
        container.addEventListener('mouseleave', start);
        container.addEventListener('focusin', stop);
        container.addEventListener('focusout', start);

        show(0);
        start();
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
                    <img src="${this.resolveImage(item.image)}" alt="${title}" loading="lazy" decoding="async">
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

    // Homepage school information hub
    if (document.querySelector('[data-school-info]')) {
        cms.renderSchoolInfoSection('[data-school-info]');
    }

    const currentRoute = cms.getCurrentRoute();

    // Homepage integrations
    if (document.querySelector('.news-grid-home')) {

        cms.renderNews('.news-section-home', 4, '.news-grid-home')
            .then(() => {
                cms.enhanceNewsLinks();
            });

    }

    // Homepage achievement showcase
    if (document.querySelector('[data-achievement-showcase]')) {
        cms.renderAchievementShowcase('[data-achievement-showcase]', 8);
    }

    // Hero slider
    if (document.querySelector('.hero__slider') || document.querySelector('.hero-slider')) {
        cms.renderSlider('.hero');
    }

    // Berita page
    if (currentRoute === 'news') {

        cms.renderNewsPage('.news-modern', 4, 8);

    }

    // Gallery page
    if (currentRoute === 'gallery') {
        cms.renderGallery('.gallery-section', 20);
    }

    // Prestasi page
    if (currentRoute === 'achievements') {
        cms.renderAchievements('.achievements-section', 12);
    }

    // Announcements
    if (document.querySelector('.announcements-list')) {
        cms.renderAnnouncements('.announcements-section', 3);
    }

});
