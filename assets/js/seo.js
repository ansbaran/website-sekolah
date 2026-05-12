document.addEventListener('DOMContentLoaded', function () {
    const pageTitle = document.title || document.querySelector('h1')?.innerText || 'SD Cahaya Harapan Bekasi';
    const descriptionSource = document.querySelector('meta[name="description"]')?.content
        || document.querySelector('p')?.innerText
        || 'Website resmi SD Cahaya Harapan Bekasi.';
    const description = descriptionSource.slice(0, 160).trim();
    const canonicalUrl = window.location.origin + window.location.pathname;

    document.title = pageTitle;

    const ensureMeta = (attrName, attrValue, content) => {
        let selector = `meta[${attrName}="${attrValue}"]`;
        let tag = document.head.querySelector(selector);
        if (!tag) {
            tag = document.createElement('meta');
            tag.setAttribute(attrName, attrValue);
            document.head.appendChild(tag);
        }
        tag.setAttribute('content', content);
    };

    ensureMeta('name', 'description', description);
    ensureMeta('property', 'og:title', pageTitle);
    ensureMeta('property', 'og:description', description);
    ensureMeta('property', 'og:type', 'website');
    ensureMeta('property', 'og:url', canonicalUrl);
    ensureMeta('name', 'twitter:card', 'summary_large_image');
    ensureMeta('name', 'twitter:title', pageTitle);
    ensureMeta('name', 'twitter:description', description);

    let canonical = document.head.querySelector('link[rel="canonical"]');
    if (!canonical) {
        canonical = document.createElement('link');
        canonical.rel = 'canonical';
        document.head.appendChild(canonical);
    }
    canonical.href = canonicalUrl;
});
