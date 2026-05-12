import { initHeroSlider } from "./slider.js";
import { initTypingAnimation } from "./typing.js";
import { initGalleryFilters } from "./gallery.js";
import { initAosAnimations } from "./aos-config.js";
import { initPageTransitions } from "./page-transition.js";

import "./navbar.js";
import "./footer.js";

import {
  initHeaderState,
  initMobileNavigation,
  initDropdownHover,
  initHashSectionNavigation,
  initActiveMenu,
  initReveal
} from "./ui.js";

import { initCounter } from "./counter.js";

function initApp() {

  initPageTransitions();

  initHeaderState();

  initMobileNavigation();

  initDropdownHover();

  initHashSectionNavigation();

  initHeroSlider();

  initTypingAnimation();

  initCounter();

  initReveal();

  initGalleryFilters();

  initActiveMenu();

  initAosAnimations();
}

document.documentElement.classList.add("js");

document.addEventListener(
  "DOMContentLoaded",
  initApp
);
