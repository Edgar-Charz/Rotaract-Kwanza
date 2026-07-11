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
