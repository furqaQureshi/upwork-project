{{-- Pull-to-refresh for touch devices (works in TWA/PWA and mobile browser) --}}
<div id="ptr-indicator"
     style="position:fixed;left:0;right:0;top:0;z-index:9999;height:52px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.08);display:flex;align-items:center;justify-content:center;gap:8px;transform:translateY(-52px);transition:transform 0.2s ease;pointer-events:none;">
    <div id="ptr-spinner"
         style="width:20px;height:20px;border-radius:50%;border:2.5px solid #fed7aa;border-top-color:#f97316;"></div>
    <span id="ptr-label"
          style="font-size:12px;font-weight:600;color:#475569;font-family:inherit;">Pull to refresh</span>
</div>
<style>
#ptr-spinner.spin{animation:ptr-spin 0.7s linear infinite}
@keyframes ptr-spin{to{transform:rotate(360deg)}}
html,body{overscroll-behavior-y:contain}
</style>
<script>
(function () {
    var indicator = document.getElementById('ptr-indicator');
    var spinner = document.getElementById('ptr-spinner');
    var label = document.getElementById('ptr-label');
    if (!indicator || !spinner || !label) {
        return;
    }

    var hasTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    if (!hasTouch) {
        return;
    }

    var H = 52;
    var THRESHOLD = 64;
    var startY = 0;
    var pulling = false;
    var curOffset = 0;
    var refreshing = false;

    document.addEventListener('touchstart', function (e) {
        if (!refreshing && window.scrollY === 0 && e.touches.length === 1) {
            startY = e.touches[0].clientY;
            pulling = true;
            curOffset = 0;
            indicator.style.transition = 'none';
        }
    }, { passive: true });

    document.addEventListener('touchmove', function (e) {
        if (!pulling || refreshing) {
            return;
        }

        var dy = e.touches[0].clientY - startY;
        if (dy <= 0 || window.scrollY > 0) {
            pulling = false;
            return;
        }

        // Prevent browser-native bounce/refresh while custom PTR is active.
        e.preventDefault();

        curOffset = Math.min(dy * 0.45, THRESHOLD + 14);
        indicator.style.transform = 'translateY(' + (curOffset - H) + 'px)';
        label.textContent = curOffset >= THRESHOLD ? 'Release to refresh' : 'Pull to refresh';
        spinner.classList.remove('spin');
    }, { passive: false });

    document.addEventListener('touchend', function () {
        if (!pulling || refreshing) {
            return;
        }

        pulling = false;
        if (curOffset >= THRESHOLD) {
            refreshing = true;
            indicator.style.transition = 'transform 0.2s ease';
            indicator.style.transform = 'translateY(0)';
            label.textContent = 'Refreshing...';
            spinner.classList.add('spin');
            setTimeout(function () {
                window.location.reload();
            }, 500);
        } else {
            indicator.style.transition = 'transform 0.25s ease';
            indicator.style.transform = 'translateY(-' + H + 'px)';
            curOffset = 0;
        }
    }, { passive: true });
})();
</script>