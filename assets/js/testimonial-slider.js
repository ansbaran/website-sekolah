const slider = document.querySelector("[data-testimonial-slider]");

if (slider) {
  const track = slider.querySelector("[data-testimonial-track]");
  const slides = Array.from(slider.querySelectorAll("[data-testimonial-slide]"));
  const previousButton = slider.querySelector("[data-testimonial-prev]");
  const nextButton = slider.querySelector("[data-testimonial-next]");
  const dotsContainer = slider.querySelector("[data-testimonial-dots]");
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  let activeIndex = 0;
  let autoplayId = 0;
  let pointerStartX = 0;
  let pointerStartY = 0;
  let pointerId = null;
  let isPointerActive = false;
  let isHorizontalDrag = false;
  let isPointerInside = false;
  let isFocusInside = false;
  let hasMovedSlide = false;

  const normalizeIndex = (index) => {
    if (slides.length === 0) return 0;
    return (index + slides.length) % slides.length;
  };

  const relativeDistance = (index) => {
    const total = slides.length;
    const forward = (index - activeIndex + total) % total;
    const backward = (activeIndex - index + total) % total;
    return forward <= backward ? forward : -backward;
  };

  const setActiveSlide = (index) => {
    activeIndex = normalizeIndex(index);

    slides.forEach((slide, slideIndex) => {
      const distance = relativeDistance(slideIndex);
      slide.classList.remove("is-active", "is-prev", "is-next", "is-far-prev", "is-far-next");

      if (distance === 0) {
        slide.classList.add("is-active");
        slide.removeAttribute("aria-hidden");
      } else if (distance === -1) {
        slide.classList.add("is-prev");
        slide.removeAttribute("aria-hidden");
      } else if (distance === 1) {
        slide.classList.add("is-next");
        slide.removeAttribute("aria-hidden");
      } else if (distance < 0) {
        slide.classList.add("is-far-prev");
        slide.setAttribute("aria-hidden", "true");
      } else {
        slide.classList.add("is-far-next");
        slide.setAttribute("aria-hidden", "true");
      }
    });

    dotsContainer?.querySelectorAll(".feedback-dot").forEach((dot, dotIndex) => {
      dot.setAttribute("aria-current", String(dotIndex === activeIndex));
    });
  };

  const stopAutoplay = () => {
    window.clearInterval(autoplayId);
    autoplayId = 0;
  };

  const startAutoplay = () => {
    if (reduceMotion.matches || autoplayId || slides.length < 2) return;

    autoplayId = window.setInterval(() => {
      setActiveSlide(activeIndex + 1);
    }, 3600);
  };

  if (track && slides.length > 0) {
    slider.classList.add("feedback-carousel--ready");

    const resumeAutoplay = () => {
      if (!isPointerInside && !isFocusInside) {
        startAutoplay();
      }
    };

    const beginPointerDrag = (event) => {
      if (event.button !== undefined && event.button !== 0) return;

      pointerId = event.pointerId;
      pointerStartX = event.clientX;
      pointerStartY = event.clientY;
      isPointerActive = true;
      isHorizontalDrag = false;
      hasMovedSlide = false;
      stopAutoplay();
      slider.classList.add("is-dragging");
      track.setPointerCapture?.(event.pointerId);
    };

    const movePointerDrag = (event) => {
      if (!isPointerActive || pointerId !== event.pointerId) return;

      const deltaX = event.clientX - pointerStartX;
      const deltaY = event.clientY - pointerStartY;
      const absX = Math.abs(deltaX);
      const absY = Math.abs(deltaY);
      const threshold = event.pointerType === "touch" ? 44 : 58;

      if (!isHorizontalDrag && absY > absX && absY > 12) {
        return;
      }

      if (absX > 12 && absX > absY * 1.15) {
        isHorizontalDrag = true;
        event.preventDefault();
      }

      if (isHorizontalDrag && !hasMovedSlide && absX >= threshold) {
        hasMovedSlide = true;
        setActiveSlide(deltaX < 0 ? activeIndex + 1 : activeIndex - 1);
      }
    };

    const endPointerDrag = (event) => {
      if (!isPointerActive || pointerId !== event.pointerId) return;

      track.releasePointerCapture?.(event.pointerId);
      pointerId = null;
      isPointerActive = false;
      isHorizontalDrag = false;
      slider.classList.remove("is-dragging");
      window.setTimeout(resumeAutoplay, 900);
    };

    dotsContainer?.replaceChildren(
      ...slides.map((slide, index) => {
        const dot = document.createElement("button");
        dot.className = "feedback-dot";
        dot.type = "button";
        dot.setAttribute("aria-label", `Lihat testimonial ${index + 1}`);
        dot.addEventListener("click", () => {
          stopAutoplay();
          setActiveSlide(index);
        });
        return dot;
      })
    );

    previousButton?.addEventListener("click", () => {
      stopAutoplay();
      setActiveSlide(activeIndex - 1);
    });

    nextButton?.addEventListener("click", () => {
      stopAutoplay();
      setActiveSlide(activeIndex + 1);
    });

    track.addEventListener("pointerdown", beginPointerDrag);
    track.addEventListener("pointermove", movePointerDrag);
    track.addEventListener("pointerup", endPointerDrag);
    track.addEventListener("pointercancel", endPointerDrag);
    track.addEventListener("pointerleave", (event) => {
      if (event.pointerType === "mouse") {
        endPointerDrag(event);
      }
    });

    track.addEventListener("keydown", (event) => {
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        stopAutoplay();
        setActiveSlide(activeIndex - 1);
      }

      if (event.key === "ArrowRight") {
        event.preventDefault();
        stopAutoplay();
        setActiveSlide(activeIndex + 1);
      }
    });

    slider.addEventListener("mouseenter", () => {
      isPointerInside = true;
      stopAutoplay();
    });

    slider.addEventListener("focusin", () => {
      isFocusInside = true;
      stopAutoplay();
    });

    slider.addEventListener("mouseleave", () => {
      isPointerInside = false;
      resumeAutoplay();
    });

    slider.addEventListener("focusout", () => {
      isFocusInside = false;
      resumeAutoplay();
    });

    setActiveSlide(0);
    startAutoplay();
  }
}
