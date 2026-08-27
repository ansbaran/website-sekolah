import { initHeroSlider } from "./slider.js";
import { initTypingAnimation } from "./typing.js";
import { initGalleryFilters } from "./gallery.js";
import { initModernStaffSection } from "./staff-modern.js?v=20260814-modern-staff";
import { initSchoolProfileContent } from "./school-profile.js?v=20260709-public-fixes";
import { initAosAnimations } from "./aos-config.js";
import { initPageTransitions } from "./page-transition.js";
import { initInstagramLatestPosts } from "./instagram.js?v=20260714-instagram-feed";

import "./navbar.js?v=20260826-clean-url1";
import "./footer.js?v=20260826-clean-url1";
import "./floating-whatsapp.js?v=20260815-whatsapp-hotfix1";

import {
  initHashSectionNavigation,
  initReveal
} from "./ui.js?v=20260826-clean-url1";

import { initCounter } from "./counter.js?v=20260824-ppdb-evergreen1";

function initApp() {

  initPageTransitions();

  initHashSectionNavigation();

  initHeroSlider();

  initTypingAnimation();

  initCounter();

  initReveal();

  initGalleryFilters();

  initInstagramLatestPosts();

  initModernStaffSection();

  initSchoolProfileContent();

  initAosAnimations();
}

document.documentElement.classList.add("js");

document.addEventListener(
  "DOMContentLoaded",
  initApp
);
