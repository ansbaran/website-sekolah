import { initHeroSlider } from "./slider.js";
import { initTypingAnimation } from "./typing.js";
import { initGalleryFilters } from "./gallery.js";
import { initStaffSection } from "./staff.js?v=20260628-staff-rail";
import { initSchoolProfileContent } from "./school-profile.js?v=20260709-public-fixes";
import { initAosAnimations } from "./aos-config.js";
import { initPageTransitions } from "./page-transition.js";
import { initInstagramLatestPosts } from "./instagram.js?v=20260714-instagram-feed";

import "./navbar.js?v=20260712-dropdown-stability";
import "./footer.js";

import {
  initHashSectionNavigation,
  initReveal
} from "./ui.js";

import { initCounter } from "./counter.js?v=20260628-staff-rail";

function initApp() {

  initPageTransitions();

  initHashSectionNavigation();

  initHeroSlider();

  initTypingAnimation();

  initCounter();

  initReveal();

  initGalleryFilters();

  initInstagramLatestPosts();

  initStaffSection();

  initSchoolProfileContent();

  initAosAnimations();
}

document.documentElement.classList.add("js");

document.addEventListener(
  "DOMContentLoaded",
  initApp
);
