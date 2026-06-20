<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($page_title ?? 'Admin') ?> — Rotaract Kwanza</title>
<link rel="stylesheet" href="<?= ADMIN_URL ?>/assets/admin.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script>
$.extend($.fn.dataTable.defaults, {
  dom: '<"dt-top-bar"f>rt<"dt-bottom-bar"<"dt-bottom-left"li><"dt-bottom-right"p>>',
  language: {
    lengthMenu: 'Show per page: _MENU_',
    info: '_START_ &ndash; _END_ of _TOTAL_ entries',
    infoEmpty: '0 entries',
    infoFiltered: '(filtered from _MAX_)',
    search: '',
    searchPlaceholder: 'Search...',
    emptyTable: 'No records found.',
    paginate: { previous: '&#8249;', next: '&#8250;' }
  }
});
</script>
</head>
<body>

<?php require_once __DIR__ . '/sidebar.php'; ?>

<?php require_once __DIR__ . '/topbar.php'; ?>
