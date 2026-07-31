const slider = document.querySelector("[data-testimonial-slider]");
const typingTarget = document.querySelector(".feedback-title__typing");
const typingLead = typingTarget?.querySelector(".feedback-title__lead");
const typingHighlight = typingTarget?.querySelector(".feedback-title__highlight");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

if (typingTarget && typingLead && typingHighlight && !reduceMotion.matches) {
  const leadText = typingLead.textContent.trim();
  const highlightText = typingHighlight.textContent.trim();
  const fullText = `${leadText} ${highlightText}`;
  const title = typingTarget.closest(".feedback-title");
  const typingCursor = document.createElement("span");
  let characterIndex = 0;
  let isDeleting = false;
  let typingTimer = 0;

  typingCursor.className = "feedback-title__cursor";
  typingCursor.setAttribute("aria-hidden", "true");
  typingCursor.textContent = "|";
  typingTarget.appendChild(typingCursor);

  typingTarget.classList.add("feedback-title__typing--active");
  typingTarget.setAttribute("aria-label", fullText);
  const targetBounds = typingTarget.getBoundingClientRect();
  const titleBounds = title?.getBoundingClientRect();
  const stableHeight = Math.ceil(Math.max(targetBounds.height, titleBounds?.height || 0, 72));
  const stableWidth = Math.ceil(Math.max(targetBounds.width, 1));

  if (title) {
    title.style.minHeight = `${stableHeight}px`;
  }

  typingTarget.style.minHeight = `${stableHeight}px`;
  typingTarget.style.minWidth = `${stableWidth}px`;
  typingTarget.style.contain = "layout paint";
  typingTarget.style.overflow = "hidden";

  const renderTypingText = () => {
    const highlightPart = highlightText.slice(0, characterIndex);

    typingLead.textContent = leadText;
    typingHighlight.textContent = highlightPart ? ` ${highlightPart}` : "";
    typingTarget.appendChild(typingCursor);

    if (!isDeleting && characterIndex >= highlightText.length) {
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
const getBasePath = () => {
  const script = document.currentScript || document.querySelector('script[src*="testimonial-slider.js"]');
  if (!script) return "";

  const scriptUrl = new URL(script.getAttribute("src"), window.location.href);
  const marker = "/assets/js/testimonial-slider.js";
  const markerIndex = scriptUrl.pathname.indexOf(marker);
  return markerIndex > 0 ? scriptUrl.pathname.slice(0, markerIndex) : "";
};

const normalizeRating = (value) => {
  const rating = Number.parseFloat(value);
  if (!Number.isFinite(rating)) return 5;
  return Math.min(5, Math.max(1, rating));
};

const createTextElement = (tagName, text, className = "") => {
  const element = document.createElement(tagName);
  if (className) element.className = className;
  element.textContent = text;
  return element;
};

const createFeedbackSlide = (item, index) => {
  const slide = document.createElement("div");
  slide.className = index === 0 ? "feedback-slide is-active" : "feedback-slide";
  slide.dataset.testimonialSlide = "";

  const card = document.createElement("article");
  card.className = "feedback-card";

  const avatar = document.createElement("div");
  const avatarClass = ["", "feedback-card__avatar--green", "feedback-card__avatar--gold", "feedback-card__avatar--indigo"][index % 4];
  avatar.className = `feedback-card__avatar${avatarClass ? ` ${avatarClass}` : ""}`;
  avatar.setAttribute("aria-hidden", "true");
  if (item.avatar_url) {
    const avatarImage = document.createElement("img");
    avatarImage.src = item.avatar_url;
    avatarImage.alt = "";
    avatarImage.loading = "lazy";
    avatarImage.decoding = "async";
    avatar.appendChild(avatarImage);
    avatar.classList.add("feedback-card__avatar--image");
  } else {
    avatar.textContent = item.initials || "OT";
  }

  const author = document.createElement("div");
  author.className = "feedback-card__author";
  author.appendChild(createTextElement("strong", item.name || "Orang tua siswa"));
  author.appendChild(createTextElement("span", item.role || "Orang tua siswa"));

  const rating = normalizeRating(item.rating);
  const ratingWrap = document.createElement("div");
  ratingWrap.className = "feedback-card__rating";
  ratingWrap.setAttribute("aria-label", `Rating ${rating.toFixed(1)} dari 5`);
  const stars = createTextElement("span", "*****");
  stars.setAttribute("aria-hidden", "true");
  ratingWrap.appendChild(stars);
  ratingWrap.appendChild(createTextElement("strong", rating.toFixed(1)));

  const message = createTextElement("p", item.message || "Feedback orang tua sudah disetujui admin.", "feedback-card__text");

  card.appendChild(avatar);
  card.appendChild(author);
  card.appendChild(ratingWrap);
  card.appendChild(message);
  slide.appendChild(card);
  return slide;
};

const createEmptySlide = () => {
  const slide = document.createElement("div");
  slide.className = "feedback-slide is-active";
  slide.dataset.testimonialSlide = "";
  slide.dataset.feedbackEmpty = "";

  const card = document.createElement("article");
  card.className = "feedback-card feedback-card--empty";

  const avatar = createTextElement("div", "OT", "feedback-card__avatar");
  avatar.setAttribute("aria-hidden", "true");

  const author = document.createElement("div");
  author.className = "feedback-card__author";
  author.appendChild(createTextElement("strong", "Feedback orang tua"));
  author.appendChild(createTextElement("span", "Menunggu data yang disetujui admin"));

  const text = createTextElement(
    "p",
    "Feedback asli dari orang tua akan tampil di sini setelah diverifikasi dan disetujui oleh admin sekolah.",
    "feedback-card__text"
  );

  card.appendChild(avatar);
  card.appendChild(author);
  card.appendChild(text);
  slide.appendChild(card);
  return slide;
};

const fetchFeedback = async () => {
  const basePath = getBasePath();
  const url = new URL(`${basePath}/api/public-feedback.php`, window.location.href);
  url.searchParams.set("limit", "6");
  url.searchParams.set("_", String(Date.now()));

  try {
    const response = await fetch(url, {
      method: "GET",
      cache: "no-store",
      headers: { Accept: "application/json" },
    });
    if (!response.ok) return [];
    const payload = await response.json();
    return payload.status === "success" && Array.isArray(payload.data) ? payload.data : [];
  } catch (error) {
    return [];
  }
};

const initFeedbackSlider = () => {
  if (!slider) return;

  slider.removeAttribute("data-aos");
  slider.removeAttribute("data-aos-delay");
  slider.removeAttribute("data-aos-custom");
  slider.classList.add("aos-animate", "aos-fallback-visible");

  const track = slider.querySelector("[data-testimonial-track]");
  const stage = slider.querySelector(".feedback-stage");
  const slides = Array.from(slider.querySelectorAll("[data-testimonial-slide]"));
  const previousButton = slider.querySelector("[data-feedback-prev], [data-testimonial-prev]");
  const nextButton = slider.querySelector("[data-feedback-next], [data-testimonial-next]");
  const dotsContainer = slider.querySelector("[data-testimonial-dots]");
  const controls = slider.querySelector(".feedback-controls");

  if (!track || slides.length === 0) return;

  const hasEmptySlide = slides.some((slide) => slide.hasAttribute("data-feedback-empty"));
  const canSlide = slides.length > 1 && !hasEmptySlide;
  let activeIndex = 0;
  let heightSyncFrame = 0;
  const mobileViewport = window.matchMedia("(max-width: 640px)");

  const normalizeIndex = (index) => (slides.length + index) % slides.length;

  const getSlidePosition = (index) => {
    if (!canSlide) return index === 0 ? "active" : "hidden";
    if (index === activeIndex) return "active";
    if (slides.length > 2 && index === normalizeIndex(activeIndex - 1)) return "prev";
    if (index === normalizeIndex(activeIndex + 1)) return "next";
    return "hidden";
  };

  const clearMobileHeights = () => {
    track.style.removeProperty("height");
    track.style.removeProperty("min-height");
    stage?.style.removeProperty("min-height");
  };

  const syncActiveSlideHeight = () => {
    if (!mobileViewport.matches) {
      clearMobileHeights();
      return;
    }

    const activeSlide = slides[activeIndex] || track.querySelector(".feedback-slide.is-active");
    const activeCard = activeSlide?.querySelector(".feedback-card");
    if (!stage || !activeSlide || !activeCard) return;

    const trackRect = track.getBoundingClientRect();
    const slideRect = activeSlide.getBoundingClientRect();
    const cardRect = activeCard.getBoundingClientRect();
    const contentBottom = Math.max(slideRect.bottom, cardRect.bottom) - trackRect.top;
    const controlsHeight = controls && !controls.hidden ? controls.getBoundingClientRect().height : 0;
    const trackHeight = Math.max(1, Math.ceil(contentBottom + 8));
    const stageHeight = Math.ceil(trackHeight + controlsHeight + (controlsHeight ? 16 : 0));

    track.style.setProperty("height", `${trackHeight}px`, "important");
    track.style.setProperty("min-height", `${trackHeight}px`, "important");
    stage.style.setProperty("min-height", `${stageHeight}px`, "important");
  };

  const queueActiveSlideHeight = () => {
    window.cancelAnimationFrame(heightSyncFrame);
    heightSyncFrame = window.requestAnimationFrame(syncActiveSlideHeight);
  };

  const setActiveSlide = (index) => {
    activeIndex = normalizeIndex(index);
    slider.classList.remove("feedback-carousel--animated");

    slides.forEach((slide, slideIndex) => {
      const position = getSlidePosition(slideIndex);
      const card = slide.querySelector(".feedback-card");
      const delay = `${Math.min(slideIndex, 5) * 90}ms`;

      slide.classList.remove("is-active", "is-prev", "is-next", "is-hidden", "is-far-prev", "is-far-next");
      slide.classList.add(`is-${position}`);
      slide.style.setProperty("--feedback-delay", delay);
      card?.style.setProperty("--feedback-card-delay", delay);

      if (position === "hidden") {
        slide.setAttribute("aria-hidden", "true");
      } else {
        slide.removeAttribute("aria-hidden");
      }
    });

    dotsContainer?.querySelectorAll(".feedback-dot").forEach((dot, dotIndex) => {
      dot.setAttribute("aria-current", String(dotIndex === activeIndex));
    });

    queueActiveSlideHeight();

    window.requestAnimationFrame(() => {
      slider.classList.add("feedback-carousel--animated");
      queueActiveSlideHeight();
    });
  };

  slider.classList.add("feedback-carousel--ready", "feedback-carousel--spotlight");
  slider.classList.remove("feedback-carousel--static");
  slider.classList.toggle("feedback-carousel--empty", hasEmptySlide);
  slider.classList.toggle("feedback-carousel--single", !canSlide);
  slider.classList.toggle("feedback-carousel--two", canSlide && slides.length === 2);

  if (controls) controls.hidden = !canSlide;
  previousButton?.toggleAttribute("disabled", !canSlide);
  nextButton?.toggleAttribute("disabled", !canSlide);

  dotsContainer?.replaceChildren(
    ...(canSlide
      ? slides.map((slide, index) => {
          const dot = document.createElement("button");
          dot.className = "feedback-dot";
          dot.type = "button";
          dot.setAttribute("aria-label", `Lihat feedback ${index + 1}`);
          dot.addEventListener("click", () => setActiveSlide(index));
          return dot;
        })
      : [])
  );

  previousButton?.addEventListener("click", () => {
    if (canSlide) setActiveSlide(activeIndex - 1);
  });

  nextButton?.addEventListener("click", () => {
    if (canSlide) setActiveSlide(activeIndex + 1);
  });

  track.addEventListener("keydown", (event) => {
    if (!canSlide) return;
    if (event.key === "ArrowLeft") {
      event.preventDefault();
      setActiveSlide(activeIndex - 1);
    }
    if (event.key === "ArrowRight") {
      event.preventDefault();
      setActiveSlide(activeIndex + 1);
    }
  });

  window.addEventListener("resize", queueActiveSlideHeight, { passive: true });
  if (typeof mobileViewport.addEventListener === "function") {
    mobileViewport.addEventListener("change", queueActiveSlideHeight);
  } else {
    mobileViewport.addListener?.(queueActiveSlideHeight);
  }
  track.querySelectorAll("img").forEach((image) => {
    if (!image.complete) {
      image.addEventListener("load", queueActiveSlideHeight, { once: true });
    }
  });
  if (document.fonts?.ready) {
    document.fonts.ready.then(queueActiveSlideHeight).catch(() => {});
  }

  setActiveSlide(0);
};
const initFeedbackSubmission = () => {
  const modal = document.querySelector("[data-feedback-modal]");
  const form = document.querySelector("[data-feedback-form]");
  const openButtons = Array.from(document.querySelectorAll("[data-feedback-open]"));
  const closeButtons = Array.from(document.querySelectorAll("[data-feedback-close]"));
  const status = document.querySelector("[data-feedback-status]");
  const submitButton = document.querySelector("[data-feedback-submit]");
  let lastFocusedElement = null;

  if (!modal || !form || openButtons.length === 0) return;

  modal.hidden = true;
  document.body.style.overflow = "";

  const setStatus = (message, type = "") => {
    if (!status) return;
    status.textContent = message;
    status.classList.remove("is-success", "is-error");
    if (type) status.classList.add(`is-${type}`);
  };

  const openModal = (trigger) => {
    lastFocusedElement = trigger || document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = "hidden";
    setStatus("");
    window.setTimeout(() => {
      const firstInput = modal.querySelector("input:not([type='hidden']):not([tabindex='-1']), select, textarea, button");
      firstInput?.focus();
    }, 30);
  };

  const closeModal = () => {
    modal.hidden = true;
    document.body.style.overflow = "";
    if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
      lastFocusedElement.focus();
    }
  };

  const validateForm = () => {
    const formData = new FormData(form);
    const parentName = String(formData.get("parent_name") || "").trim();
    const parentEmail = String(formData.get("parent_email") || "").trim();
    const relationLabel = String(formData.get("relation_label") || "").trim();
    const message = String(formData.get("message") || "").trim();
    const rating = Number.parseFloat(String(formData.get("rating") || "5"));
    const consentGiven = formData.get("consent_given") === "1";

    if (parentName.length < 2) return "Nama yang ditampilkan minimal 2 karakter.";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(parentEmail)) return "Email aktif wajib diisi dengan format yang benar.";
    if (relationLabel.length < 2) return "Keterangan orang tua/wali wajib dipilih.";
    if (message.length < 20) return "Masukan minimal 20 karakter agar konteksnya jelas.";
    if (message.length > 700) return "Masukan maksimal 700 karakter.";
    if (!Number.isFinite(rating) || rating < 1 || rating > 5) return "Rating harus berada di antara 1 sampai 5.";
    if (!consentGiven) return "Centang izin publikasi agar feedback dapat ditinjau admin.";
    return "";
  };

  openButtons.forEach((button) => {
    button.addEventListener("click", () => openModal(button));
  });

  closeButtons.forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      closeModal();
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !modal.hidden) {
      closeModal();
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const validationMessage = validateForm();
    if (validationMessage) {
      setStatus(validationMessage, "error");
      return;
    }

    const endpoint = new URL(`${getBasePath()}/api/submit-feedback.php`, window.location.href);
    const formData = new FormData(form);

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = "Mengirim...";
    }
    setStatus("Mengirim feedback...", "");

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        body: formData,
        headers: { Accept: "application/json" },
      });
      const payload = await response.json().catch(() => ({}));

      if (!response.ok || payload.status !== "success") {
        setStatus(payload.message || "Feedback belum dapat dikirim. Silakan coba lagi.", "error");
        return;
      }

      form.reset();
      setStatus(payload.message || "Terima kasih. Feedback Anda menunggu persetujuan admin.", "success");
    } catch (error) {
      setStatus("Koneksi bermasalah. Silakan coba lagi beberapa saat.", "error");
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = "Kirim Feedback";
      }
    }
  });
};

initFeedbackSubmission();
const hydrateFeedback = async () => {
  if (!slider) return;
  const track = slider.querySelector("[data-testimonial-track]");
  if (!track) return;

  const feedbackItems = await fetchFeedback();
  track.replaceChildren(
    ...(feedbackItems.length > 0 ? feedbackItems.map(createFeedbackSlide) : [createEmptySlide()])
  );
  track.querySelector("[data-testimonial-slide]")?.classList.add("is-active");
  initFeedbackSlider();
};

hydrateFeedback();
