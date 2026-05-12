const SLIDER_SELECTOR = "[data-slide]";
const ACTIVE_CLASS = "hero__slide--active";
const INTERVAL = 4500;

export function initHeroSlider() {
  const slides = [...document.querySelectorAll(SLIDER_SELECTOR)];
  const nextBtn = document.querySelector(".hero__nav--next");
  const prevBtn = document.querySelector(".hero__nav--prev");

  let activeIndex = 0;

  if (slides.length <= 1) return;

  function showSlide(index) {
    slides.forEach((slide) => slide.classList.remove(ACTIVE_CLASS));
    slides[index].classList.add(ACTIVE_CLASS);
  }

  function nextSlide() {
    activeIndex = (activeIndex + 1) % slides.length;
    showSlide(activeIndex);
  }

  function prevSlide() {
    activeIndex = (activeIndex - 1 + slides.length) % slides.length;
    showSlide(activeIndex);
  }

  // auto slide
  let autoSlide = setInterval(nextSlide, INTERVAL);

  // tombol manual
  nextBtn?.addEventListener("click", () => {
    nextSlide();
    resetAutoSlide();
  });

  prevBtn?.addEventListener("click", () => {
    prevSlide();
    resetAutoSlide();
  });

  function resetAutoSlide() {
    clearInterval(autoSlide);
    autoSlide = setInterval(nextSlide, INTERVAL);
  }
}