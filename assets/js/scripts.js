document.documentElement.classList.add("js");
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
      a.classList.toggle("active", a.dataset.nav === pinned);
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
        if (e.isIntersecting) {
          requestAnimationFrame(() => {
            requestAnimationFrame(() => {
              e.target.classList.add("visible");
            });
          });
        }
      });
    },
    { threshold: 0.01, rootMargin: "0px 0px -5% 0px" }
  );

  // Some public pages are made from a single standalone card instead of the
  // section/header/card pattern used on the homepage. Give those top-level
  // blocks the same entrance animation, but never add a second animation to
  // a block that already contains deliberately staggered reveals.
  document
    .querySelectorAll(
      "main > .container > *, main > section > .container > *, .rsvp-card, .profile-shell, .profile-missing, .error-404-section"
    )
    .forEach((block) => {
      if (!block.classList.contains("reveal") && !block.querySelector(".reveal")) {
        block.classList.add("reveal");
      }
    });
  document.querySelectorAll(".reveal").forEach((el) => observer.observe(el));

  // Whole event card is clickable through to the event detail page, but clicks
  // on an actual link inside it (RSVP, Details, title) keep going to their own
  // destination instead of being overridden by the card-wide handler.
  document.querySelectorAll(".event-card[data-href]").forEach((card) => {
    card.addEventListener("click", (e) => {
      if (e.target.closest("a")) return;
      window.location.href = card.dataset.href;
    });
    card.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !e.target.closest("a")) window.location.href = card.dataset.href;
    });
  });

  initPhotoSliders();

  // Copy-link buttons in social share rows (news posts, event pages)
  document.querySelectorAll(".share-copy-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const url = btn.dataset.shareUrl;
      if (!url || !navigator.clipboard) return;
      navigator.clipboard.writeText(url).then(() => {
        const original = btn.textContent;
        btn.textContent = "Copied!";
        setTimeout(() => { btn.textContent = original; }, 2000);
      });
    });
  });

  // Instagram/TikTok share buttons: neither platform accepts a pre-filled
  // link on the web, so copy the URL and open the platform so the visitor
  // can paste it into a Story/bio/DM themselves — the standard workaround
  // for platforms with no web share intent.
  document.querySelectorAll(".share-copy-open-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      const url = btn.dataset.shareUrl;
      const openUrl = btn.dataset.openUrl;
      // Open synchronously, inside the click handler itself — same as the
      // plain <a target="_blank"> buttons next to it. Chaining this inside
      // the clipboard promise below (as before) meant it silently did
      // nothing on any origin without clipboard access (non-HTTPS/non-
      // localhost) and could get flagged as an unrequested popup by
      // browsers that only treat *synchronous* click handlers as trusted.
      if (openUrl) window.open(openUrl, "_blank", "noopener");
      if (url && navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
          const original = btn.textContent;
          btn.textContent = "Link copied!";
          setTimeout(() => { btn.textContent = original; }, 2500);
        });
      }
    });
  });
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

// Copy-to-clipboard for donate.php's bank/mobile money detail cards
document.querySelectorAll(".donate-btn-copy").forEach((btn) => {
  btn.addEventListener("click", () => {
    const target = document.getElementById(btn.dataset.copyTarget);
    if (!target || !navigator.clipboard) return;
    navigator.clipboard.writeText(target.textContent.trim()).then(() => {
      const original = btn.textContent;
      btn.textContent = "Copied!";
      setTimeout(() => (btn.textContent = original), 1500);
    });
  });
});

// Thousands-separator formatting for money amount fields (class="money-input").
// Fields are type="text" so commas can display; the value is stripped back to
// a plain number on submit, and again server-side as a defense-in-depth check.
(function () {
  function formatIntPart(digits) {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function reformat(el) {
    const before = el.value;
    const caret = el.selectionStart ?? before.length;

    let cleaned = before.replace(/[^\d.]/g, "");
    const firstDot = cleaned.indexOf(".");
    if (firstDot !== -1) {
      cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, "");
    }
    const [intRaw, decRaw] = cleaned.split(".");
    const intPart = formatIntPart(intRaw || "");
    const next = decRaw !== undefined ? intPart + "." + decRaw.slice(0, 2) : intPart;

    el.value = next;
    const pos = Math.min(next.length, Math.max(0, caret + (next.length - before.length)));
    el.setSelectionRange(pos, pos);
  }

  document.addEventListener("input", (e) => {
    if (e.target.matches && e.target.matches(".money-input")) reformat(e.target);
  });

  document.addEventListener(
    "blur",
    (e) => {
      if (!(e.target.matches && e.target.matches(".money-input"))) return;
      const el = e.target;
      const num = parseFloat(el.value.replace(/,/g, ""));
      el.value = isNaN(num) ? "" : num.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    true
  );

  document.addEventListener("submit", (e) => {
    e.target.querySelectorAll && e.target.querySelectorAll(".money-input").forEach((el) => {
      el.value = el.value.replace(/,/g, "");
    });
  });
})();

// Let the entire public member card open its profile without taking over the
// separate email and social-media links inside the card.
document.querySelectorAll("[data-profile-url]").forEach((card) => {
  const openProfile = () => { window.location.href = card.dataset.profileUrl; };
  card.addEventListener("click", (event) => {
    if (!event.target.closest("a, button")) openProfile();
  });
  card.addEventListener("keydown", (event) => {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      openProfile();
    }
  });
});

// Homepage spotlight cards can link as a whole while preserving their
// individual links, such as a recipient's member profile.
document.querySelectorAll("[data-card-url]").forEach((card) => {
  const openCard = () => { window.location.href = card.dataset.cardUrl; };
  card.addEventListener("click", (event) => {
    if (!event.target.closest("a, button")) openCard();
  });
  card.addEventListener("keydown", (event) => {
    if (event.target !== card) return;
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      openCard();
    }
  });
});

// In-Page Instant Live Filter & Auto-Clear for search forms (input[name="q"])
(function () {
  document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('form input[name="q"]').forEach((input) => {
      const form = input.form;
      if (!form) return;

      // Find target search cards/items on the current page
      const cards = document.querySelectorAll(
        ".project-card, .event-card, .dir-card, .team-card, .news-card, .gallery-item, .history-term-block, table.admin-table tbody tr"
      );
      if (!cards.length) return;

      const countEl = document.querySelector(
        ".dir-count, .events-results-count, .projects-results-count"
      );
      const initialCountText = countEl ? countEl.textContent : "";

      // Dynamic empty state element when 0 cards match the typed query
      let noResultsEl = document.getElementById("search-no-results-live");
      if (!noResultsEl && cards[0] && cards[0].parentElement) {
        noResultsEl = document.createElement("div");
        noResultsEl.id = "search-no-results-live";
        noResultsEl.className = "gallery-empty reveal visible";
        noResultsEl.style.display = "none";
        noResultsEl.style.textAlign = "center";
        noResultsEl.style.padding = "60px 20px";
        noResultsEl.innerHTML = `
          <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="var(--text-soft)" stroke-width="1.5" style="margin-bottom:12px;opacity:0.5;">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <p class="fs-110 fw-700" style="margin-bottom:6px;">No matching results found</p>
          <p class="text-soft fs-95">Try typing a different keyword or clear the search field.</p>
        `;
        const cardContainer = cards[0].parentElement;
        if (cardContainer.parentElement) {
          cardContainer.parentElement.appendChild(noResultsEl);
        }
      }

      function filterItems() {
        const query = input.value.toLowerCase().trim();
        let matchCount = 0;

        cards.forEach((card) => {
          const text = card.textContent.toLowerCase();
          const matches = query === "" || text.includes(query);
          if (matches) {
            card.style.display = "";
            card.classList.add("visible");
            matchCount++;
          } else {
            card.style.display = "none";
          }
        });

        // Update count text live
        if (countEl) {
          if (query !== "") {
            countEl.textContent = `${matchCount} result${matchCount !== 1 ? "s" : ""} matching "${input.value.trim()}"`;
          } else {
            countEl.textContent = initialCountText;
          }
        }

        // Toggle in-page empty state message
        if (noResultsEl) {
          noResultsEl.style.display = matchCount === 0 && query !== "" ? "block" : "none";
        }
      }

      // Filter instantly on every keystroke
      input.addEventListener("input", filterItems);
      input.addEventListener("search", filterItems);

      // Prevent page reload on form submit
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        filterItems();
      });

      // Filter immediately if field contains initial text
      if (input.value.trim() !== "") {
        filterItems();
      }
    });
  });
})();
