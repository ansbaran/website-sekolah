const siteRootUrl = new URL('../../', import.meta.url);

function normalizeFooterLinks(container) {
    container.querySelectorAll('a[href]').forEach((link) => {
        const href = link.getAttribute('href');

        if (
            !href ||
            /^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i.test(href)
        ) {
            return;
        }

        link.href = new URL(href, siteRootUrl).href;
    });
}

async function loadFooter() {
    const footer = document.getElementById('footer');
    if (!footer) return;

    const candidatePaths = [
        new URL('components/footer.html', siteRootUrl).href,
        new URL('components/footer.html', window.location.origin + '/').href
    ];


    for (const componentUrl of candidatePaths) {
        try {
            const response = await fetch(componentUrl, { cache: 'no-cache' });

            if (!response.ok) {
                continue;
            }

            const data = await response.text();
            footer.innerHTML = data;
            normalizeFooterLinks(footer);
            return;
        } catch (error) {
            continue;
        }
    }

}

loadFooter();
