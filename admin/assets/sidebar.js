/* ==========================================================================
   Sidebar + Topbar behavior — pairs with sidebar.css.

   Drop-in usage in another project: load this after the DOM it controls
   exists (end of <body> works, same as this project's footer.php) and wire
   up the onclick handlers shown in sidebar.css's header comment. Everything
   here is self-contained — no dependency on this project's admin.js.

   The two exceptions are the inline <script> blocks still living directly in
   sidebar.php (restoring the rail/accordion state from localStorage): those
   run at their exact position in the markup, before first paint, to avoid a
   flash of un-collapsed content — moving them into this file (which loads
   later, at the end of body) would reintroduce that flash. Everything below
   is fine to load later since it only reacts to user interaction.
   ========================================================================== */

/* ── Mobile off-canvas drawer ────────────────────────────────────────────
   Toggled by the topbar hamburger (.sidebar-toggle) and the backdrop click. */
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  sidebar.classList.toggle("open");
  document.body.style.overflow = sidebar.classList.contains("open")
    ? "hidden"
    : "";
}

document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    const sidebar = document.getElementById("sidebar");
    if (sidebar) sidebar.classList.remove("open");
    document.body.style.overflow = "";
  }
});

/* ── Collapsible nav groups (accordion) ───────────────────────────────────
   Expanding a group here is scoped to the current page view — navigating to
   a different page resets to just that page's group open (see sidebar.php's
   inline restore script), so a group you opened to browse doesn't stay stuck
   open after you've moved on. */
function toggleNavGroup(btn) {
  const section = btn.closest(".nav-section");
  if (!section) return;
  const collapsed = section.classList.toggle("collapsed");
  btn.setAttribute("aria-expanded", collapsed ? "false" : "true");
}

/* ── Collapsible icon-rail mode ────────────────────────────────────────────
   Unlike the accordion state above, this is a standing layout preference —
   it persists across navigation via localStorage. Desktop-only: below the
   900px drawer breakpoint (must match sidebar.css) the sidebar is already an
   overlay, so collapsing it to icons doesn't make sense — the toggle button
   is hidden there via CSS, and this is a matching guard for anyone still
   calling the function directly (e.g. from devtools or a keyboard shortcut). */
var SIDEBAR_RAIL_MIN_WIDTH = 901;

function toggleSidebarRail() {
  if (window.innerWidth < SIDEBAR_RAIL_MIN_WIDTH) return;
  const sidebar = document.getElementById("sidebar");
  if (!sidebar) return;
  const railed = sidebar.classList.toggle("rail");
  try {
    localStorage.setItem("sidebarRail", railed ? "1" : "0");
  } catch (e) {}
}

/* Keeps the 'rail' class in sync when a window resize crosses the drawer
   breakpoint without a full page reload — e.g. rotating a tablet, or
   shrinking a desktop browser window. Shrinking below the breakpoint drops
   the class (CSS alone can't remove a class, only override its styles);
   growing back above it restores whatever was last remembered, matching
   what a fresh page load would have shown at that width. */
(function () {
  var wasNarrow = window.innerWidth < SIDEBAR_RAIL_MIN_WIDTH;
  window.addEventListener("resize", function () {
    var isNarrow = window.innerWidth < SIDEBAR_RAIL_MIN_WIDTH;
    if (isNarrow === wasNarrow) return;
    wasNarrow = isNarrow;

    var sidebar = document.getElementById("sidebar");
    if (!sidebar) return;
    if (isNarrow) {
      sidebar.classList.remove("rail");
    } else {
      var stored;
      try {
        stored = localStorage.getItem("sidebarRail");
      } catch (e) {
        stored = null;
      }
      sidebar.classList.toggle("rail", stored === "1");
    }
  });
})();

/* ── Rail-mode flyout submenus ──────────────────────────────────────────
   In rail mode a group's .nav-section-body is position:fixed (see sidebar.css
   for why) and hidden until hovered/focused, so it needs JS to compute where
   to place it next to the icon. A short close delay lets the pointer travel
   from the icon to the panel without it disappearing first. */
(function () {
  var CLOSE_DELAY = 200;
  var closeTimer = null;
  var openSection = null;

  function positionFlyout(section) {
    var toggle = section.querySelector(".nav-section-toggle");
    var body = section.querySelector(".nav-section-body");
    if (!toggle || !body) return;
    var rect = toggle.getBoundingClientRect();
    body.style.top = Math.round(rect.top) + "px";
    body.style.left = Math.round(rect.right + 6) + "px";
  }

  function openFlyout(sidebar, section) {
    if (!sidebar.classList.contains("rail")) return;
    clearTimeout(closeTimer);
    if (openSection && openSection !== section)
      openSection.classList.remove("flyout-open");
    positionFlyout(section);
    section.classList.add("flyout-open");
    openSection = section;
  }

  function scheduleClose(section) {
    clearTimeout(closeTimer);
    closeTimer = setTimeout(function () {
      section.classList.remove("flyout-open");
      if (openSection === section) openSection = null;
    }, CLOSE_DELAY);
  }

  var sidebar = document.getElementById("sidebar");
  if (!sidebar) return;

  sidebar
    .querySelectorAll(".nav-section[data-group]")
    .forEach(function (section) {
      var toggle = section.querySelector(".nav-section-toggle");
      if (!toggle || !section.querySelector(".nav-section-body")) return;

      section.addEventListener("mouseenter", function () {
        openFlyout(sidebar, section);
      });
      section.addEventListener("mouseleave", function () {
        scheduleClose(section);
      });
      toggle.addEventListener("focus", function () {
        openFlyout(sidebar, section);
      });
      toggle.addEventListener("blur", function () {
        scheduleClose(section);
      });
    });

  window.addEventListener("resize", function () {
    if (openSection) openSection.classList.remove("flyout-open");
    openSection = null;
  });
})();

/* ── Smooth page transitions on sidebar navigation ───────────────────────
   Fades the page out briefly before following a sidebar link, purely for
   polish — the navigation itself still happens via a normal link. */
(function () {
  var TRANSITION_MS = 150;

  document.addEventListener("click", function (e) {
    var link = e.target.closest(".sidebar .nav-item");
    if (!link || !link.href) return;
    if (link.target === "_blank" || link.hasAttribute("download")) return;
    if (
      e.defaultPrevented ||
      e.button !== 0 ||
      e.metaKey ||
      e.ctrlKey ||
      e.shiftKey ||
      e.altKey
    )
      return;

    var dest = new URL(link.href, location.href);
    if (dest.href.split("#")[0] === location.href.split("#")[0]) return; // same page

    e.preventDefault();
    document.body.classList.add("page-transitioning");
    setTimeout(function () {
      location.href = link.href;
    }, TRANSITION_MS);
  });

  // Pages restored from bfcache (e.g. browser back button) keep the class
  // from the outgoing click — clear it so the restored page is visible.
  window.addEventListener("pageshow", function () {
    document.body.classList.remove("page-transitioning");
  });
})();

/* ── Topbar notification bell ────────────────────────────────────────── */
(function () {
  var bell = document.getElementById("notif-bell");
  var dropdown = document.getElementById("notif-dropdown");
  if (!bell || !dropdown) return;

  bell.addEventListener("click", function (e) {
    e.stopPropagation();
    var open = dropdown.classList.toggle("open");
    bell.setAttribute("aria-expanded", open ? "true" : "false");
  });

  document.addEventListener("click", function (e) {
    if (!dropdown.contains(e.target) && e.target !== bell) {
      dropdown.classList.remove("open");
      bell.setAttribute("aria-expanded", "false");
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      dropdown.classList.remove("open");
      bell.setAttribute("aria-expanded", "false");
    }
  });
})();

/* ── Topbar live search dropdown ─────────────────────────────────────────
   Hits search_ajax.php as you type and renders grouped results. Kept
   self-contained (own debounce/fetch/escape helpers) rather than sharing
   admin.js's copies, so this file has no cross-file dependency. */
(function () {
  var topInput = document.getElementById("topbar-search-input");
  var dropdown = document.getElementById("search-dropdown");
  var searchForm = document.getElementById("topbar-search-form");
  if (!topInput || !dropdown) return;

  var debounceTimer = null;
  var currentXhr = null;

  function esc(val) {
    var d = document.createElement("div");
    d.textContent =
      val === null || val === undefined || val === "" ? "—" : String(val);
    return d.innerHTML;
  }

  function debounce(fn, ms) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fn, ms);
  }

  function abort() {
    if (currentXhr) {
      currentXhr.abort();
      currentXhr = null;
    }
  }

  function fetchSearch(q, done) {
    abort();
    var xhr = new XMLHttpRequest();
    xhr.open("GET", "search_ajax.php?q=" + encodeURIComponent(q), true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      if (xhr.status === 200) {
        try {
          done(JSON.parse(xhr.responseText));
        } catch (e) {}
      }
    };
    xhr.send();
    currentXhr = xhr;
  }

  function badge(label, cls) {
    return (
      '<span class="badge badge-' +
      esc((cls || label).toLowerCase()) +
      '">' +
      esc(label) +
      "</span>"
    );
  }

  function renderDropdown(data, q) {
    if (data.total === 0) {
      dropdown.innerHTML =
        '<div class="sd-empty">No results for <strong>' +
        esc(q) +
        "</strong></div>";
      dropdown.classList.add("open");
      return;
    }

    var html = "";
    Object.keys(data.sources || {}).forEach(function (key) {
      var src = data.sources[key];
      html +=
        '<div class="sd-section"><div class="sd-section-title">' +
        esc(src.label) +
        "</div>";
      src.items.slice(0, 5).forEach(function (item) {
        html +=
          '<a class="sd-item" href="' +
          esc(item.href) +
          '">' +
          '<span class="sd-item-main">' +
          esc(item.title || "") +
          "</span>" +
          '<span class="sd-item-sub">' +
          esc(item.subtitle || "") +
          "</span>" +
          (item.badge !== null && item.badge !== undefined
            ? badge(item.badge, item.badge_class)
            : "") +
          "</a>";
      });
      html += "</div>";
    });

    html +=
      '<a class="sd-footer" href="search.php?q=' +
      encodeURIComponent(q) +
      '">See all ' +
      data.total +
      " results &rarr;</a>";
    dropdown.innerHTML = html;
    dropdown.classList.add("open");
  }

  function closeDropdown() {
    dropdown.classList.remove("open");
  }

  topInput.addEventListener("input", function () {
    var q = this.value.trim();
    if (q.length < 2) {
      closeDropdown();
      abort();
      return;
    }
    dropdown.innerHTML = '<div class="sd-spinner"></div>';
    dropdown.classList.add("open");
    debounce(function () {
      fetchSearch(q, function (data) {
        renderDropdown(data, q);
      });
    }, 250);
  });

  // Prevent form submit on Enter while the dropdown is open — navigate instead.
  if (searchForm) {
    searchForm.addEventListener("submit", function (e) {
      var q = topInput.value.trim();
      if (q.length >= 2) {
        e.preventDefault();
        window.location.href = "search.php?q=" + encodeURIComponent(q);
      }
    });
  }

  document.addEventListener("click", function (e) {
    if (searchForm && !searchForm.contains(e.target)) closeDropdown();
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") closeDropdown();
  });
})();
