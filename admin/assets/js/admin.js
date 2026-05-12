document.addEventListener('DOMContentLoaded', function () {
    const toggleButton = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.admin-sidebar');

    if (toggleButton && sidebar) {
        toggleButton.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    document.querySelectorAll('[data-confirm]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            const message = button.getAttribute('data-confirm') || 'Apakah Anda yakin?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const previewModal = document.getElementById('media-preview-modal');
    const previewImage = document.getElementById('media-modal-image');
    const previewTitle = document.getElementById('media-modal-title');
    const closeModal = document.getElementById('close-media-modal');

    if (previewModal) {
        document.querySelectorAll('.preview-button').forEach(function (button) {
            button.addEventListener('click', function () {
                previewImage.src = button.dataset.imageSrc;
                previewImage.alt = button.dataset.imageTitle || 'Preview gambar';
                previewTitle.textContent = button.dataset.imageTitle || '';
                previewModal.hidden = false;
            });
        });

        if (closeModal) {
            closeModal.addEventListener('click', function () {
                previewModal.hidden = true;
                previewImage.src = '';
            });
        }

        previewModal.querySelector('.media-modal__overlay')?.addEventListener('click', function () {
            previewModal.hidden = true;
            previewImage.src = '';
        });
    }

    document.querySelectorAll('[data-copy-url]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const url = button.dataset.copyUrl;
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

    if (dropZone && fileInput && previewList) {
        const updatePreview = () => {
            previewList.innerHTML = '';
            Array.from(fileInput.files).forEach((file) => {
                const item = document.createElement('div');
                item.className = 'preview-card';
                item.innerHTML = `
                    <div style="width:72px; height:72px; overflow:hidden; border-radius:14px; background:#0f172a; display:flex; align-items:center; justify-content:center;">
                        <img src="${URL.createObjectURL(file)}" alt="Preview" style="width:100%; height:100%; object-fit:cover; display:block;" />
                    </div>
                    <div class="preview-content">
                        <strong>${file.name}</strong>
                        <small>${(file.size / 1024).toFixed(0)} KB</small>
                    </div>
                `;
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
    }

    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function () {
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                searchInput.form?.submit();
            }, 450);
        });
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

    if (extendSessionButton) {
        extendSessionButton.addEventListener('click', function () {
            window.location.reload();
        });
    }

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            if (loadingOverlay) {
                loadingOverlay.hidden = false;
            }
        });
    });

    function showToast(message) {
        const container = document.querySelector('.toast-container');
        if (!container) {
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
        const toastContainer = document.querySelector('.toast-container');
        if (toastContainer) {
            showToast(alert.textContent.trim());
            alert.remove();
        }
    });
});
