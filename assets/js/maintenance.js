const maintenanceScript = document.currentScript;
const maintenanceScriptSource = maintenanceScript
  ? maintenanceScript.getAttribute('src')
  : 'assets/js/maintenance.js';
const maintenanceScriptUrl = new URL(
  maintenanceScriptSource,
  document.baseURI
);
const maintenanceSiteRootUrl = new URL(
  '../../',
  maintenanceScriptUrl
);
const maintenanceUrl = new URL(
  'maintenance',
  maintenanceSiteRootUrl
);
const maintenanceApiUrl = new URL(
  'api/maintenance.php',
  maintenanceSiteRootUrl
);

document.addEventListener('DOMContentLoaded', async function () {
  const currentPath = window.location.pathname.replace(
    /\/+$/,
    ''
  );
  const maintenancePath = maintenanceUrl.pathname.replace(
    /\/+$/,
    ''
  );

  if (
    window.location.pathname.includes('/admin') ||
    currentPath === maintenancePath
  ) {
        return;
    }

    try {
        const response = await fetch(maintenanceApiUrl.href, {
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) {
            return;
        }

        const data = await response.json();
        if (data?.data?.maintenance) {
            window.location.replace(maintenanceUrl.href);
        }
    } catch (error) {
    }
});
