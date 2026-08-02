function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  sidebar.classList.toggle('open');
  document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}

function openModal(id) {
  const m = document.getElementById(id);
  if (m) bootstrap.Modal.getOrCreateInstance(m).show();
}

function closeModal(id) {
  const m = document.getElementById(id);
  if (m) bootstrap.Modal.getOrCreateInstance(m).hide();
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.remove('open');
    document.body.style.overflow = '';
  }
});

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

/* ── Live / real-time search ─────────────────────────────────────────────── */
(function () {
  var debounceTimer = null;
  var currentXhr    = null;

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

  /* ── badge helper ── */
  function badge(status) {
    return '<span class="badge badge-' + esc(status) + '">' + esc(status) + '</span>';
  }

  /* ── Topbar dropdown ─────────────────────────────────────────────────── */
  var topInput    = document.getElementById('topbar-search-input');
  var dropdown    = document.getElementById('search-dropdown');
  var searchForm  = document.getElementById('topbar-search-form');

  function renderDropdown(data, q) {
    if (!dropdown) return;
    if (data.total === 0) {
      dropdown.innerHTML = '<div class="sd-empty">No results for <strong>' + esc(q) + '</strong></div>';
      dropdown.classList.add('open');
      return;
    }

    var html = '';

    if (data.members.length) {
      html += '<div class="sd-section"><div class="sd-section-title">Members</div>';
      data.members.slice(0, 5).forEach(function (m) {
        html += '<a class="sd-item" href="members.php">' +
          '<span class="sd-item-main">' + esc(m.first_name + ' ' + m.last_name) + '</span>' +
          '<span class="sd-item-sub">' + esc(m.email) + '</span>' +
          badge(m.status) + '</a>';
      });
      html += '</div>';
    }

    if (data.events.length) {
      html += '<div class="sd-section"><div class="sd-section-title">Events</div>';
      data.events.slice(0, 5).forEach(function (e) {
        var date = e.event_date ? e.event_date.substring(0, 10) : '';
        html += '<a class="sd-item" href="events.php">' +
          '<span class="sd-item-main">' + esc(e.title) + '</span>' +
          '<span class="sd-item-sub">' + esc(date) + '</span>' +
          badge(e.status) + '</a>';
      });
      html += '</div>';
    }

    if (data.announcements.length) {
      html += '<div class="sd-section"><div class="sd-section-title">Announcements</div>';
      data.announcements.slice(0, 5).forEach(function (p) {
        html += '<a class="sd-item" href="announcements.php">' +
          '<span class="sd-item-main">' + esc(p.title) + '</span>' +
          '<span class="sd-item-sub">' + esc(p.category) + '</span>' +
          (p.is_published ? '<span class="badge badge-approved">Published</span>' : '<span class="badge badge-pending">Draft</span>') +
          '</a>';
      });
      html += '</div>';
    }

    if (data.messages.length) {
      html += '<div class="sd-section"><div class="sd-section-title">Messages</div>';
      data.messages.slice(0, 5).forEach(function (msg) {
        html += '<a class="sd-item" href="messages.php?view=' + encodeURIComponent(msg.id) + '">' +
          '<span class="sd-item-main">' + esc(msg.full_name) + '</span>' +
          '<span class="sd-item-sub">' + esc(msg.subject || '(no subject)') + '</span>' +
          badge(msg.status) + '</a>';
      });
      html += '</div>';
    }

    html += '<a class="sd-footer" href="search.php?q=' + encodeURIComponent(q) + '">See all ' + data.total + ' results &rarr;</a>';
    dropdown.innerHTML = html;
    dropdown.classList.add('open');
  }

  function closeDropdown() {
    if (dropdown) dropdown.classList.remove('open');
  }

  if (topInput && dropdown) {
    topInput.addEventListener('input', function () {
      var q = this.value.trim();
      if (q.length < 2) { closeDropdown(); abort(); return; }
      dropdown.innerHTML = '<div class="sd-spinner"></div>';
      dropdown.classList.add('open');
      debounce(function () { fetchSearch(q, function (data) { renderDropdown(data, q); }); }, 250);
    });

    /* prevent form submit on Enter if dropdown is open — navigate instead */
    searchForm && searchForm.addEventListener('submit', function (e) {
      var q = topInput.value.trim();
      if (q.length >= 2) {
        e.preventDefault();
        window.location.href = 'search.php?q=' + encodeURIComponent(q);
      }
    });

    document.addEventListener('click', function (e) {
      if (searchForm && !searchForm.contains(e.target)) closeDropdown();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeDropdown();
    });
  }

  /* ── Search page: live results ───────────────────────────────────────── */
  var pageInput   = document.getElementById('search-page-input');
  var resultsDiv  = document.getElementById('search-results');
  var statusDiv   = document.getElementById('search-status');

  function buildSection(title, allHref, allLabel, theadHtml, rows) {
    if (!rows.length) return '';
    return '<div class="card mb-2">' +
      '<div class="card-header"><span class="card-title">' + esc(title) + ' (' + rows.length + ')</span>' +
      '<a href="' + allHref + '" class="btn btn-sm btn-secondary">' + esc(allLabel) + '</a></div>' +
      '<div class="table-wrap"><table><thead>' + theadHtml + '</thead><tbody>' +
      rows.join('') + '</tbody></table></div></div>';
  }

  function renderPageResults(data, q) {
    if (!resultsDiv) return;
    if (data.total === 0) {
      resultsDiv.innerHTML = '';
      if (statusDiv) statusDiv.innerHTML = 'No results found for <strong>' + esc(q) + '</strong>.';
      return;
    }

    if (statusDiv) statusDiv.innerHTML = data.total + ' result' + (data.total !== 1 ? 's' : '') + ' for <strong>' + esc(q) + '</strong>';

    var html = '';

    if (data.members.length) {
      var rows = data.members.map(function (m) {
        return '<tr><td class="fw-bold">' + esc(m.first_name + ' ' + m.last_name) + '</td>' +
          '<td>' + esc(m.email) + '</td>' +
          '<td>' + badge(m.status) + '</td>' +
          '<td><a href="members.php" class="btn btn-sm btn-secondary">View</a></td></tr>';
      });
      html += buildSection('Members', 'members.php', 'All Members',
        '<tr><th>Name</th><th>Email</th><th>Status</th><th></th></tr>', rows);
    }

    if (data.events.length) {
      var rows = data.events.map(function (e) {
        var date = e.event_date ? e.event_date.substring(0, 10) : '—';
        return '<tr><td class="fw-bold">' + esc(e.title) + '</td>' +
          '<td class="text-muted">' + esc(date) + '</td>' +
          '<td>' + badge(e.status) + '</td>' +
          '<td><a href="rsvps.php?event=' + encodeURIComponent(e.id) + '" class="btn btn-sm btn-secondary">RSVPs</a></td></tr>';
      });
      html += buildSection('Events', 'events.php', 'All Events',
        '<tr><th>Title</th><th>Date</th><th>Status</th><th></th></tr>', rows);
    }

    if (data.announcements.length) {
      var rows = data.announcements.map(function (p) {
        var pub = p.is_published ? '<span class="badge badge-approved">Published</span>' : '<span class="badge badge-pending">Draft</span>';
        return '<tr><td class="fw-bold">' + esc(p.title) + '</td>' +
          '<td>' + esc(p.category) + '</td><td>' + pub + '</td></tr>';
      });
      html += buildSection('Announcements', 'announcements.php', 'All Posts',
        '<tr><th>Title</th><th>Category</th><th>Status</th></tr>', rows);
    }

    if (data.messages.length) {
      var rows = data.messages.map(function (msg) {
        return '<tr><td><div class="fw-bold">' + esc(msg.full_name) + '</div>' +
          '<div class="text-muted" style="font-size:11.5px">' + esc(msg.email) + '</div></td>' +
          '<td>' + esc(msg.subject || '(no subject)') + '</td>' +
          '<td>' + badge(msg.status) + '</td>' +
          '<td><a href="messages.php?view=' + encodeURIComponent(msg.id) + '" class="btn btn-sm btn-secondary">View</a></td></tr>';
      });
      html += buildSection('Messages', 'messages.php', 'All Messages',
        '<tr><th>From</th><th>Subject</th><th>Status</th><th></th></tr>', rows);
    }

    resultsDiv.innerHTML = html;
  }

  if (pageInput && resultsDiv) {
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
  }
})();
