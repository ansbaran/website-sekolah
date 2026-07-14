document.addEventListener('DOMContentLoaded', function () {
    const toggleButton = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.admin-sidebar');
    const sidebarBackdrop = document.querySelector('[data-sidebar-close]');

    const closeSidebar = () => {
        sidebar?.classList.remove('open');
        document.body.classList.remove('sidebar-open');
        toggleButton?.setAttribute('aria-expanded', 'false');
    };

    if (toggleButton && sidebar) {
        toggleButton.addEventListener('click', function () {
            const isOpen = sidebar.classList.toggle('open');
            document.body.classList.toggle('sidebar-open', isOpen);
            toggleButton.setAttribute('aria-expanded', String(isOpen));
        });
    }

    sidebarBackdrop?.addEventListener('click', closeSidebar);
    document.querySelectorAll('.sidebar-link').forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
            closeMediaModal();
        }
    });

    document.querySelectorAll('[data-confirm]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            const message = button.getAttribute('data-confirm') || 'Apakah Anda yakin?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('img').forEach(function (image) {
        image.addEventListener('error', function () {
            const fallback = image.dataset.fallbackSrc || '../assets/img/logo.png';
            if (image.src.endsWith(fallback)) {
                image.classList.add('is-broken');
                return;
            }
            image.src = fallback;
            image.classList.add('is-broken');
        });
    });

    const previewModal = document.getElementById('media-preview-modal');
    const previewImage = document.getElementById('media-modal-image');
    const previewTitle = document.getElementById('media-modal-title');
    const closeModal = document.getElementById('close-media-modal');

    function closeMediaModal() {
        if (!previewModal) {
            return;
        }
        previewModal.hidden = true;
        if (previewImage) {
            previewImage.removeAttribute('src');
        }
    }

    if (previewModal && previewImage) {
        document.querySelectorAll('.preview-button').forEach(function (button) {
            button.addEventListener('click', function () {
                const imageSrc = button.dataset.imageSrc || '';
                const imageTitle = button.dataset.imageTitle || 'Preview gambar';
                previewImage.src = imageSrc;
                previewImage.alt = imageTitle;
                if (previewTitle) {
                    previewTitle.textContent = imageTitle;
                }
                previewModal.hidden = false;
            });
        });

        closeModal?.addEventListener('click', closeMediaModal);
        previewModal.querySelector('.media-modal__overlay')?.addEventListener('click', closeMediaModal);
    }

    document.querySelectorAll('[data-copy-url]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const url = button.dataset.copyUrl || '';
            try {
                await navigator.clipboard.writeText(url);
                showToast('URL berhasil disalin.');
            } catch (error) {
                window.prompt('Salin URL berikut:', url);
            }
        });
    });

    const dropZone = document.getElementById('media-drop-zone');
    const fileInput = document.getElementById('images');
    const previewList = document.getElementById('media-preview-list');
    let previewUrls = [];

    if (dropZone && fileInput && previewList) {
        const clearPreviewUrls = () => {
            previewUrls.forEach((url) => URL.revokeObjectURL(url));
            previewUrls = [];
        };

        const updatePreview = () => {
            clearPreviewUrls();
            previewList.replaceChildren();

            Array.from(fileInput.files).forEach((file) => {
                const previewUrl = URL.createObjectURL(file);
                previewUrls.push(previewUrl);

                const item = document.createElement('div');
                item.className = 'preview-card';

                const image = document.createElement('img');
                image.src = previewUrl;
                image.alt = file.name;

                const content = document.createElement('div');
                content.className = 'preview-content';

                const title = document.createElement('h3');
                title.textContent = file.name;

                const size = document.createElement('small');
                size.textContent = `${(file.size / 1024).toFixed(0)} KB`;

                content.appendChild(title);
                content.appendChild(size);
                item.appendChild(image);
                item.appendChild(content);
                previewList.appendChild(item);
            });
        };

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropZone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropZone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropZone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropZone.classList.remove('drag-over');
            });
        });

        dropZone.addEventListener('drop', function (event) {
            const files = event.dataTransfer?.files;
            if (!files) {
                return;
            }
            fileInput.files = files;
            updatePreview();
        });

        dropZone.addEventListener('click', function () {
            fileInput.click();
        });

        fileInput.addEventListener('change', updatePreview);
        window.addEventListener('beforeunload', clearPreviewUrls);
    }

    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function () {
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                searchInput.form?.submit();
            }, 550);
        });
    }

    const titleInput = document.getElementById('title');
    const slugPreview = document.getElementById('slug-preview');

    if (titleInput && slugPreview) {
        const updateSlugPreview = () => {
            const title = titleInput.value.trim();
            if (title === '') {
                slugPreview.textContent = 'Slug: -';
                return;
            }

            const slug = title.toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)/g, '')
                .substring(0, 200);
            slugPreview.textContent = `Slug: ${slug}`;
        };

        titleInput.addEventListener('input', updateSlugPreview);
        updateSlugPreview();
    }

    const sessionModal = document.getElementById('session-expiry-modal');
    const extendSessionButton = document.getElementById('extend-session-button');
    const loadingOverlay = document.getElementById('loading-overlay');
    const bodyElement = document.querySelector('body');
    const sessionTimeout = Number(bodyElement?.dataset.sessionTimeout || 0);
    let sessionRemaining = Number(bodyElement?.dataset.sessionRemaining || 0);

    if (sessionModal && sessionTimeout > 0) {
        const sessionTick = () => {
            sessionRemaining = Math.max(0, sessionRemaining - 1);
            if (sessionRemaining <= 120) {
                sessionModal.hidden = false;
            }
            if (sessionRemaining === 0) {
                window.location.reload();
            }
        };
        window.setInterval(sessionTick, 1000);
    }

    extendSessionButton?.addEventListener('click', function () {
        window.location.reload();
    });

    const formatBytes = (bytes) => {
        if (!Number.isFinite(bytes)) {
            return '';
        }

        const mb = bytes / 1024 / 1024;
        return `${mb.toFixed(mb >= 10 ? 0 : 1)}MB`;
    };

    const validateFileSizes = (form) => {
        const fileInputs = form.querySelectorAll('input[type="file"]');
        const maxSizeDefault = 4 * 1024 * 1024;

        for (const input of fileInputs) {
            const maxSize = Number(input.dataset.maxSize || maxSizeDefault);
            const files = Array.from(input.files || []);
            const oversized = files.find((file) => file.size > maxSize);

            if (oversized) {
                const message = `File "${oversized.name}" terlalu besar. Maksimal ${formatBytes(maxSize)} per file. Data lain tetap aman, silakan pilih file yang lebih kecil.`;
                showToast(message);
                window.alert(message);
                input.focus();
                return false;
            }
        }

        return true;
    };

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!validateFileSizes(form)) {
                event.preventDefault();
                if (loadingOverlay) {
                    loadingOverlay.hidden = true;
                }
                return;
            }

            if (loadingOverlay && String(form.method).toLowerCase() !== 'get') {
                loadingOverlay.hidden = false;
            }
        });
    });

    function showToast(message) {
        const container = document.querySelector('.toast-container');
        if (!container || !message) {
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('visible'), 20);
        setTimeout(() => toast.remove(), 4200);
    }

    document.querySelectorAll('.alert').forEach((alert) => {
        showToast(alert.textContent.trim());
    });
});
