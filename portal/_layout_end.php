    </div><!-- /.content -->
  </div><!-- /.main -->
</div><!-- /.layout -->

<script>
(function() {
  'use strict';
  var sidebar  = document.getElementById('sidebar');
  var overlay  = document.getElementById('overlay');
  var burger   = document.getElementById('burger');
  if (!sidebar || !burger) return;

  function open()  { sidebar.classList.add('open');  overlay.classList.add('open');  burger.setAttribute('aria-expanded','true');  }
  function close() { sidebar.classList.remove('open'); overlay.classList.remove('open'); burger.setAttribute('aria-expanded','false'); }

  burger.addEventListener('click', function() {
    sidebar.classList.contains('open') ? close() : open();
  });
  overlay.addEventListener('click', close);
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
})();
</script>
</body>
</html>
