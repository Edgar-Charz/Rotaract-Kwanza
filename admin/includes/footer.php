  </main><!-- .content -->
</div><!-- .main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/admin.js"></script>
<script>
  (function () {
    var params = new URLSearchParams(location.search);
    if (params.get('new') === '1' && document.getElementById('add-modal')) {
      openModal('add-modal');
    }
  })();
</script>
</body>
</html>
