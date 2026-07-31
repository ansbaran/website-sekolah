(function () {
  'use strict';

  const measurementId = 'G-VFFH0BPFY4';

  if (window.__sdchGa4Initialized) {
    return;
  }

  window.__sdchGa4Initialized = true;
  window.dataLayer = window.dataLayer || [];

  window.gtag = window.gtag || function () {
    window.dataLayer.push(arguments);
  };

  const existingLoader = document.querySelector(
    'script[src*="googletagmanager.com/gtag/js?id=' +
      measurementId +
      '"]'
  );

  if (!existingLoader) {
    const loader = document.createElement('script');
    loader.async = true;
    loader.src =
      'https://www.googletagmanager.com/gtag/js?id=' +
      encodeURIComponent(measurementId);
    document.head.appendChild(loader);
  }

  window.gtag('js', new Date());

  window.gtag('config', measurementId, {
    allow_google_signals: false,
    allow_ad_personalization_signals: false
  });
})();
