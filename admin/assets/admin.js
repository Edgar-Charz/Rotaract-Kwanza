/* Sidebar/topbar behavior (toggle, accordion, rail, flyouts, notification
   bell, topbar search) now lives in sidebar.js — loaded alongside this file.
   Everything below is general admin-UI behavior used across pages. */

function openModal(id) {
  const m = document.getElementById(id);
  if (m) bootstrap.Modal.getOrCreateInstance(m).show();
}

function closeModal(id) {
  const m = document.getElementById(id);
  if (m) bootstrap.Modal.getOrCreateInstance(m).hide();
}

function confirmDelete(formId) {
  if (confirm('Are you sure you want to delete this item? This cannot be undone.')) {
    document.getElementById(formId).submit();
  }
}

function confirmAction(msg, formId) {
  if (confirm(msg)) {
    document.getElementById(formId).submit();
  }
}

function previewImage(input, previewId) {
  const preview = document.getElementById(previewId || 'img-preview');
  if (input.files && input.files[0] && preview) {
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.src = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
    // Picking a replacement makes any pending "remove image" checkbox moot —
    // uncheck it so the two controls can't silently contradict each other
    // (the server would honor the new upload over "remove" anyway, but a
    // checked box next to a fresh preview reads as a mistake waiting to happen).
    const removeBox = input.closest('.image-field')?.querySelector('input[type="checkbox"][name^="remove_"]');
    if (removeBox) removeBox.checked = false;
  }
}

function esc(val) {
  const d = document.createElement('div');
  d.textContent = (val === null || val === undefined || val === '') ? '—' : String(val);
  return d.innerHTML;
}

function filterTable(inputId, tableId) {
  const val = document.getElementById(inputId).value.toLowerCase();
  const rows = document.querySelectorAll('#' + tableId + ' tbody tr');
  rows.forEach(function(row) {
    row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
  });
}

function populateEditModal(fields) {
  Object.entries(fields).forEach(function([key, val]) {
    const el = document.getElementById('edit_' + key) || document.querySelector('[name="' + key + '"][data-form="edit"]');
    if (el) el.value = val;
  });
}

/* Thousands-separator formatting for money amount fields (class="money-input").
   Fields are type="text" so commas can display; the value is stripped back to
   a plain number on submit, and again server-side as a defense-in-depth check. */
(function () {
  function formatIntPart(digits) {
    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function reformat(el) {
    var before = el.value;
    var caret = el.selectionStart == null ? before.length : el.selectionStart;

    var cleaned = before.replace(/[^\d.]/g, '');
    var firstDot = cleaned.indexOf('.');
    if (firstDot !== -1) {
      cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
    }
    var split = cleaned.split('.');
    var intPart = formatIntPart(split[0] || '');
    var next = split.length > 1 ? intPart + '.' + split[1].slice(0, 2) : intPart;

    el.value = next;
    var pos = Math.min(next.length, Math.max(0, caret + (next.length - before.length)));
    el.setSelectionRange(pos, pos);
  }

  document.addEventListener('input', function (e) {
    if (e.target.matches && e.target.matches('.money-input')) reformat(e.target);
  });

  document.addEventListener('blur', function (e) {
    if (!(e.target.matches && e.target.matches('.money-input'))) return;
    var el = e.target;
    var num = parseFloat(el.value.replace(/,/g, ''));
    el.value = isNaN(num) ? '' : num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }, true);

  document.addEventListener('submit', function (e) {
    if (!e.target.querySelectorAll) return;
    e.target.querySelectorAll('.money-input').forEach(function (el) {
      el.value = el.value.replace(/,/g, '');
    });
  });
})();

(function() {
  const flash = document.getElementById('flash-msg');
  if (flash) {
    setTimeout(function() {
      flash.style.opacity = '0';
      flash.style.transition = 'opacity 0.4s';
      setTimeout(function() { flash.remove(); }, 400);
    }, 4000);
  }
})();

/* ── Search page: live results (search.php) ──────────────────────────────
   Own local debounce/fetch/escape helpers rather than sharing sidebar.js's
   topbar-search copies, so the two features don't depend on load order. */
(function () {
  var pageInput  = document.getElementById('search-page-input');
  var resultsDiv = document.getElementById('search-results');
  var statusDiv  = document.getElementById('search-status');
  if (!pageInput || !resultsDiv) return;

  var debounceTimer = null;
  var currentXhr = null;

  function debounce(fn, ms) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fn, ms);
  }

  function abort() {
    if (currentXhr) { currentXhr.abort(); currentXhr = null; }
  }

  function fetchSearch(q, done) {
    abort();
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'search_ajax.php?q=' + encodeURIComponent(q), true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      if (xhr.status === 200) {
        try { done(JSON.parse(xhr.responseText)); } catch (e) {}
      }
    };
    xhr.send();
    currentXhr = xhr;
  }

  function badge(label, cls) {
    return '<span class="badge badge-' + esc((cls || label).toLowerCase()) + '">' + esc(label) + '</span>';
  }

  function buildSection(src) {
    var hasBadges = src.items.some(function (i) { return i.badge !== null && i.badge !== undefined; });
    var rows = src.items.map(function (item) {
      return '<tr><td class="fw-bold">' + esc(item.title || '') + '</td>' +
        '<td class="text-muted">' + esc(item.subtitle || '') + '</td>' +
        (hasBadges ? '<td>' + (item.badge !== null && item.badge !== undefined ? badge(item.badge, item.badge_class) : '') + '</td>' : '') +
        '<td><a href="' + esc(item.href) + '" class="btn btn-sm btn-secondary">View</a></td></tr>';
    });
    return '<div class="card mb-2">' +
      '<div class="card-header"><span class="card-title">' + esc(src.label) + ' (' + src.items.length + ')</span>' +
      '<a href="' + esc(src.all_href) + '" class="btn btn-sm btn-secondary">View All</a></div>' +
      '<div class="table-wrap"><table><thead><tr><th>Title</th><th>Detail</th>' +
      (hasBadges ? '<th>Status</th>' : '') + '<th></th></tr></thead><tbody>' +
      rows.join('') + '</tbody></table></div></div>';
  }

  function renderPageResults(data, q) {
    if (data.total === 0) {
      resultsDiv.innerHTML = '';
      if (statusDiv) statusDiv.innerHTML = 'No results found for <strong>' + esc(q) + '</strong>.';
      return;
    }

    if (statusDiv) statusDiv.innerHTML = data.total + ' result' + (data.total !== 1 ? 's' : '') + ' for <strong>' + esc(q) + '</strong>';

    var html = '';
    Object.keys(data.sources || {}).forEach(function (key) {
      html += buildSection(data.sources[key]);
    });
    resultsDiv.innerHTML = html;
  }

  pageInput.addEventListener('input', function () {
    var q = this.value.trim();
    history.replaceState(null, '', q.length >= 2 ? '?q=' + encodeURIComponent(q) : '?');
    if (q.length < 2) {
      abort();
      resultsDiv.innerHTML = '';
      if (statusDiv) statusDiv.innerHTML = q.length === 1 ? 'Enter at least 2 characters.' : '';
      return;
    }
    if (statusDiv) statusDiv.innerHTML = 'Searching…';
    debounce(function () { fetchSearch(q, function (data) { renderPageResults(data, q); }); }, 220);
  });
})();
