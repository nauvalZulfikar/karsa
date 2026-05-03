{{-- PWA Install Banner --}}
<div id="pwa-install-banner">
    <span>📱 Install DPUTR PM di HP kamu untuk akses lebih cepat</span>
    <div style="display:flex;gap:8px;flex-shrink:0">
        <button id="pwa-install-btn">Install</button>
        <button class="dismiss" id="pwa-dismiss-btn">Nanti</button>
    </div>
</div>

<script>
(function () {
    let deferredPrompt = null;
    const banner    = document.getElementById('pwa-install-banner');
    const installBtn= document.getElementById('pwa-install-btn');
    const dismissBtn= document.getElementById('pwa-dismiss-btn');

    if (sessionStorage.getItem('pwa-dismissed')) return;

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;
        banner.classList.add('show');
    });

    installBtn && installBtn.addEventListener('click', function () {
        banner.classList.remove('show');
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function () { deferredPrompt = null; });
        }
    });

    dismissBtn && dismissBtn.addEventListener('click', function () {
        banner.classList.remove('show');
        sessionStorage.setItem('pwa-dismissed', '1');
    });

    window.addEventListener('appinstalled', function () {
        banner.classList.remove('show');
        deferredPrompt = null;
    });
})();
</script>
