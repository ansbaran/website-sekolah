document.addEventListener('DOMContentLoaded', async function loadFooter() {

    const footer = document.getElementById("footer");

    if (!footer) {
        console.warn('Footer element not found');
        return;
    }

    try {

        const response = await fetch("components/footer.html", {
            cache: 'no-cache'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.text();

        footer.innerHTML = data;

        console.log('Footer loaded successfully');

    } catch (error) {

        console.error("Footer failed to load:", error);

    }
});