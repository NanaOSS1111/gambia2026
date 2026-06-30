<?php /* Page-load spinner — included immediately after <body> in every HTML page */ ?>
<div id="page-loader" aria-hidden="true">
  <div class="pl-ring"></div>
</div>
<style>
  #page-loader {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: 99999;
    background: rgba(255, 255, 255, 0.93);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: opacity 0.35s ease;
  }
  #page-loader.hidden {
    opacity: 0;
    pointer-events: none;
  }
  .pl-ring {
    width: 52px;
    height: 52px;
    border: 5px solid #e2e8f0;
    border-top-color: #1a56db;
    border-radius: 50%;
    animation: pl-spin 0.75s linear infinite;
  }
  @keyframes pl-spin {
    to { transform: rotate(360deg); }
  }
</style>
<script>
  (function () {
    var loader = document.getElementById('page-loader');
    var startTime = Date.now();
    var MIN_MS = 500;

    function hide() {
      var elapsed = Date.now() - startTime;
      var delay = Math.max(0, MIN_MS - elapsed);
      setTimeout(function () {
        loader.classList.add('hidden');
        setTimeout(function () { loader.style.display = 'none'; }, 380);
      }, delay);
    }

    if (document.readyState === 'complete') {
      hide();
    } else {
      window.addEventListener('load', hide);
    }
  })();
</script>
