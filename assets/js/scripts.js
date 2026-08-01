document.body.classList.add("js");

// Progress Bar + Scroll Spy + Back-to-top visibility
window.addEventListener("scroll", () => {
  const h = document.documentElement;
  const pct = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
  const bar = document.getElementById("progress-bar");
  if (bar) bar.style.width = pct + "%";
  const nav = document.getElementById("navbar");
  if (nav) nav.classList.toggle("scrolled", h.scrollTop > 20);
  scrollSpy();
  const backToTop = document.getElementById("back-to-top");
  if (backToTop) backToTop.classList.toggle("visible", h.scrollTop > 400);
});

function scrollSpy() {
  const nav = document.getElementById("navbar");
  const pinned = nav?.dataset.activeNav;
  if (pinned) {
    document.querySelectorAll("#nav-links a").forEach((a) => {
      const href = a.getAttribute("href") || "";
      a.classList.toggle("active", href.endsWith("#" + pinned));
    });
    return;
  }

  const sections = document.querySelectorAll("section[id]");
  if (!sections.length) return;
  let activeId = sections[0].id;
  sections.forEach((section) => {
    if (section.getBoundingClientRect().top <= 100) activeId = section.id;
  });
  document.querySelectorAll("#nav-links a").forEach((a) => {
    const href = a.getAttribute("href") || "";
    const matches = href === "#" + activeId || href.endsWith("#" + activeId);
    a.classList.toggle("active", matches);
  });
}

// Mobile Menu
function toggleMenu() {
  const links = document.getElementById("nav-links");
  if (links) links.classList.toggle("open");
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".nav-links a").forEach((a) => {
    a.addEventListener("click", () => {
      const links = document.getElementById("nav-links");
      if (links) links.classList.remove("open");
    });
  });

  scrollSpy();

  // Back to top
  const backToTop = document.getElementById("back-to-top");
  if (backToTop) {
    backToTop.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  // Guard public forms against double-submit: disable the submit button
  // once a submission starts. These forms do a full page reload/redirect
  // on success, so there's no matching re-enable — a timeout re-enables it
  // only as a safety net in case validation silently blocks submission.
  document.querySelectorAll("form").forEach((form) => {
    form.addEventListener("submit", () => {
      const btn = form.querySelector('button[type="submit"], input[type="submit"]');
      if (!btn || btn.classList.contains("is-submitting")) return;
      btn.classList.add("is-submitting");
      setTimeout(() => btn.classList.remove("is-submitting"), 8000);
    });
  });

  // Scroll reveal
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((e) => {
        if (e.isIntersecting) e.target.classList.add("visible");
      });
    },
    { threshold: 0.12 }
  );
  document.querySelectorAll(".reveal").forEach((el) => observer.observe(el));

  initPhotoSliders();
});

// Photo slider (group photos) — supports any number of independent sliders
// on one page, e.g. one per leadership term on leadership_history.php.
function initPhotoSliders() {
  document.querySelectorAll(".photo-slider").forEach((slider) => {
    const track = slider.querySelector(".photo-slider-track");
    const slides = slider.querySelectorAll(".photo-slider-slide");
    const dots = slider.querySelectorAll(".photo-slider-dot");
    const prevBtn = slider.querySelector(".photo-slider-btn.prev");
    const nextBtn = slider.querySelector(".photo-slider-btn.next");
    if (!track || slides.length <= 1) return;

    let current = 0;
    const goTo = (i) => {
      current = (i + slides.length) % slides.length;
      track.style.transform = `translateX(-${current * 100}%)`;
      dots.forEach((d, di) => d.classList.toggle("active", di === current));
    };

    if (prevBtn) prevBtn.addEventListener("click", () => goTo(current - 1));
    if (nextBtn) nextBtn.addEventListener("click", () => goTo(current + 1));
    dots.forEach((d, di) => d.addEventListener("click", () => goTo(di)));
  });
}
