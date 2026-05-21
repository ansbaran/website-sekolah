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


    for (const componentUrl of candidatePaths) {
        try {
            const response = await fetch(componentUrl, { cache: 'no-cache' });

            if (!response.ok) {
                continue;
            }

            const data = await response.text();
            footer.innerHTML = data;
            return;
        } catch (error) {
            continue;
        }
    }

}

loadFooter();
