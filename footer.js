async function loadFooter() {

    const footer = document.getElementById("footer");

    if (!footer) return;

    try {

        const response = await fetch("components/footer.html");

        const data = await response.text();

        footer.innerHTML = data;

    } catch (error) {

        console.error("Footer gagal dimuat:", error);

    }
}

loadFooter();