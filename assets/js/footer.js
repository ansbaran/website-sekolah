async function loadFooter() {
    const footer = document.getElementById('footer');
    if (!footer) return;

    const pageDirectory = window.location.pathname.replace(/\/[^\/]*$/, '/');
    const rootPath = pageDirectory.includes('/website-sekolah/')
        ? '/website-sekolah/'
        : '/';

    const candidatePaths = [
        new URL('../../components/footer.html', import.meta.url).href,
        `${window.location.origin}${pageDirectory}components/footer.html`,
        `${window.location.origin}${rootPath}components/footer.html`,
        `${window.location.origin}/components/footer.html`
    ];

    console.log('[Footer] mencari komponen di path:', candidatePaths);

    for (const componentUrl of candidatePaths) {
        try {
            const response = await fetch(componentUrl, { cache: 'no-cache' });

            if (!response.ok) {
                console.warn('[Footer] path tidak ditemukan:', componentUrl, response.status);
                continue;
            }

            const data = await response.text();
            footer.innerHTML = data;
            console.log('[Footer] berhasil dimuat dari:', componentUrl);
            return;
        } catch (error) {
            console.warn('[Footer] gagal memuat path:', componentUrl, error);
        }
    }

    console.error('Footer gagal dimuat: komponen footer tidak ditemukan pada semua path fallback');
}

loadFooter();
