function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
}

document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(function(m) {
      m.classList.remove('open');
    });
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
