document.addEventListener('DOMContentLoaded', async function () {
    if (window.location.pathname.includes('/admin') || window.location.pathname.endsWith('/maintenance.html')) {
        return;
    }

    try {
        const response = await fetch('api/maintenance.php', {
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) {
            return;
        }

        const data = await response.json();
        if (data?.data?.maintenance) {
            window.location.replace('/maintenance.html');
        }
    } catch (error) {
    }
});
