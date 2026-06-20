// Progress Bar + Scroll Spy
window.addEventListener("scroll", () => {
  const h = document.documentElement;
  const pct = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
  const bar = document.getElementById("progress-bar");
  if (bar) bar.style.width = pct + "%";
  const nav = document.getElementById("navbar");
  if (nav) nav.classList.toggle("scrolled", h.scrollTop > 20);
  scrollSpy();
});

function scrollSpy() {
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
});
