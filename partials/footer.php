  </div>
</div>
<script>
(function () {
  var drawer = document.getElementById('drawer');
  var scrim = document.getElementById('scrim');
  var button = document.getElementById('menu-button');
  function set(open) {
    drawer.classList.toggle('open', open);
    scrim.classList.toggle('open', open);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  button.addEventListener('click', function () { set(!drawer.classList.contains('open')); });
  scrim.addEventListener('click', function () { set(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { set(false); } });
})();
</script>
</body>
</html>
