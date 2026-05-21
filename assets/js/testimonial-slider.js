const slider = document.querySelector("[data-testimonial-slider]");
const typingTarget = document.querySelector(".feedback-title__typing");
const typingLead = typingTarget?.querySelector(".feedback-title__lead");
const typingHighlight = typingTarget?.querySelector(".feedback-title__highlight");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

if (typingTarget && typingLead && typingHighlight && !reduceMotion.matches) {
  const leadText = typingLead.textContent.trim();
  const highlightText = typingHighlight.textContent.trim();
  const fullText = `${leadText} ${highlightText}`;
  let characterIndex = 0;
  let isDeleting = false;
  let typingTimer = 0;

  typingTarget.classList.add("feedback-title__typing--active");
  typingTarget.setAttribute("aria-label", fullText);

  const renderTypingText = () => {
    const currentText = fullText.slice(0, characterIndex);
    const leadPart = currentText.slice(0, Math.min(currentText.length, leadText.length));
    const highlightPart = currentText.length > leadText.length
      ? currentText.slice(leadText.length + 1)
      : "";

    typingLead.textContent = leadPart;
    typingHighlight.textContent = highlightPart ? ` ${highlightPart}` : "";

    if (!isDeleting && characterIndex >= fullText.length) {
      isDeleting = true;
      typingTimer = window.setTimeout(renderTypingText, 1700);
      return;
    }

    if (isDeleting && characterIndex <= 0) {
      isDeleting = false;
      typingTimer = window.setTimeout(renderTypingText, 450);
      return;
    }

    characterIndex += isDeleting ? -1 : 1;
    typingTimer = window.setTimeout(renderTypingText, isDeleting ? 34 : 58);
  };

  renderTypingText();

  window.addEventListener("pagehide", () => {
    window.clearTimeout(typingTimer);
  });
}

if (slider) {
  const track = slider.querySelector("[data-testimonial-track]");
  const dragSurface = slider.querySelector(".feedback-stage") || track;
  const slides = Array.from(slider.querySelectorAll("[data-testimonial-slide]"));
  const previousButton = slider.querySelector("[data-feedback-prev], [data-testimonial-prev]");
  const nextButton = slider.querySelector("[data-feedback-next], [data-testimonial-next]");
  const dotsContainer = slider.querySelector("[data-testimonial-dots]");

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

  if (track && dragSurface && slides.length > 0) {
    slider.classList.add("feedback-carousel--ready");

    const resumeAutoplay = () => {
      if (!isPointerInside && !isFocusInside) {
        startAutoplay();
      }
    };

    const beginPointerDrag = (event) => {
      if (event.button !== undefined && event.button !== 0) return;
      if (event.target.closest("button, .feedback-dot, [data-feedback-prev], [data-feedback-next], [data-feedback-dot], [data-testimonial-prev], [data-testimonial-next]")) return;

      pointerId = event.pointerId;
      pointerStartX = event.clientX;
      pointerStartY = event.clientY;
      isPointerActive = true;
      isHorizontalDrag = false;
      hasMovedSlide = false;
      stopAutoplay();
      slider.classList.add("is-dragging");
      dragSurface.setPointerCapture?.(event.pointerId);
    };

    const movePointerDrag = (event) => {
      if (!isPointerActive || pointerId !== event.pointerId) return;

      const deltaX = event.clientX - pointerStartX;
      const deltaY = event.clientY - pointerStartY;
      const absX = Math.abs(deltaX);
      const absY = Math.abs(deltaY);
      const threshold = event.pointerType === "touch" ? 38 : 44;

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

      dragSurface.releasePointerCapture?.(event.pointerId);
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
        dot.dataset.feedbackDot = String(index);
        dot.setAttribute("aria-label", `Lihat testimonial ${index + 1}`);
        dot.addEventListener("click", (event) => {
          event.preventDefault();
          event.stopPropagation();
          stopAutoplay();
          setActiveSlide(index);
        });
        return dot;
      })
    );

    previousButton?.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      stopAutoplay();
      setActiveSlide(activeIndex - 1);
    });

    nextButton?.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      stopAutoplay();
      setActiveSlide(activeIndex + 1);
    });

    dragSurface.addEventListener("pointerdown", beginPointerDrag);
    dragSurface.addEventListener("pointermove", movePointerDrag);
    dragSurface.addEventListener("pointerup", endPointerDrag);
    dragSurface.addEventListener("pointercancel", endPointerDrag);
    dragSurface.addEventListener("pointerleave", (event) => {
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
