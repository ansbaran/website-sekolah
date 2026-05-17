import { initHeroSlider } from "./slider.js";
import { initTypingAnimation } from "./typing.js";
import { initGalleryFilters } from "./gallery.js";
import { initAosAnimations } from "./aos-config.js";
import { initPageTransitions } from "./page-transition.js";

import "./navbar.js";
import "./footer.js";

import {
  initHashSectionNavigation,
  initReveal
} from "./ui.js";

import { initCounter } from "./counter.js";

function initApp() {

  initPageTransitions();

  initHashSectionNavigation();

  initHeroSlider();

  initTypingAnimation();

  initCounter();

  initReveal();

  initGalleryFilters();

  initAosAnimations();
}

document.documentElement.classList.add("js");

document.addEventListener(
  "DOMContentLoaded",
  initApp
);
