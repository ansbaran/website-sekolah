const TYPING_SELECTOR = "[data-typing]";
const TYPE_SPEED = 80;
const DELETE_SPEED = 40;
const HOLD_DELAY = 1500;

export function initTypingAnimation() {
  const element = document.querySelector(TYPING_SELECTOR);

  if (!element) {
    return;
  }

  // parsing flexible
  let words;
  try {
    words = JSON.parse(element.dataset.typing);
  } catch {
    words = [element.dataset.typing];
  }

  if (!words || words.length === 0) {
    return;
  }

  element.style.minWidth = "0";
  element.style.contain = "content";
  element.style.overflow = "visible";
  element.style.whiteSpace = "nowrap";
  element.setAttribute("aria-label", words.join(", "));

  let wordIndex = 0;
  let charIndex = 0;
  let isDeleting = false;

  function loop() {
    const currentWord = words[wordIndex];

    if (!isDeleting) {
      charIndex++;
    } else {
      charIndex--;
    }

    element.textContent = currentWord.substring(0, charIndex) || "\u00a0";

    let speed = isDeleting ? DELETE_SPEED : TYPE_SPEED;

    // selesai mengetik
    if (!isDeleting && charIndex === currentWord.length) {
      speed = HOLD_DELAY;
      isDeleting = true;
    }

    // selesai menghapus
    else if (isDeleting && charIndex === 0) {
      isDeleting = false;
      wordIndex = (wordIndex + 1) % words.length;
      speed = 300;
    }

    setTimeout(loop, speed);
  }

  loop(); // ðŸ”¥ INI PENTING (sebelumnya hilang)
}
