export function initCounter() {
  const counters = document.querySelectorAll("[data-target]");

  if (!counters.length) return;

  const animate = (el, target) => {
    let current = 0;
    const speed = target / 80;

    function update() {
      current += speed;

      if (current < target) {
        el.textContent = Math.floor(current);
        requestAnimationFrame(update);
      } else {
        el.textContent = target + "+";
        el.dataset.animated = "true";
      }
    }

    update();
  };

  const reset = (el) => {
    el.textContent = "0";
    el.dataset.animated = "false";
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const el = entry.target;
      const target = el.dataset.target;

      if (!target) return;

      if (entry.isIntersecting) {
        // 🔥 hanya jalan kalau belum animasi
        if (el.dataset.animated !== "true") {
          animate(el, +target);
        }
      } else {
        // 🔥 reset saat keluar viewport
        reset(el);
      }
    });
  }, {
    threshold: 0.6
  });

  counters.forEach((el) => {
    el.dataset.animated = "false";
    observer.observe(el);
  });
}
// =========================
// COUNTDOWN PPDB
// =========================

const countdownEl = document.getElementById("countdown");
const daysEl = document.getElementById("days");
const hoursEl = document.getElementById("hours");
const minutesEl = document.getElementById("minutes");
const secondsEl = document.getElementById("seconds");
const hasCountdown =
  countdownEl && daysEl && hoursEl && minutesEl && secondsEl;

const targetDate = new Date("May 31, 2026 23:59:59").getTime();

function updateCountdown() {
  if (!hasCountdown) return;

  const now = new Date().getTime();
  const distance = targetDate - now;

  if (distance < 0) {
    countdownEl.innerHTML = "Pendaftaran ditutup";
    return;
  }

  const days = Math.floor(distance / (1000 * 60 * 60 * 24));
  const hours = Math.floor((distance / (1000 * 60 * 60)) % 24);
  const minutes = Math.floor((distance / (1000 * 60)) % 60);
  const seconds = Math.floor((distance / 1000) % 60);

  daysEl.textContent = days;
  hoursEl.textContent = hours;
  minutesEl.textContent = minutes;
  secondsEl.textContent = seconds;
}

if (hasCountdown) {
  // jalankan setiap 1 detik
  setInterval(updateCountdown, 1000);

  // jalankan sekali saat load
  updateCountdown();
}
