{{-- PWA & Mobile Meta Tags --}}
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="DPUTR PM">
<meta name="theme-color" content="#f59e0b">
<meta name="msapplication-TileColor" content="#f59e0b">
<meta name="format-detection" content="telephone=yes">

<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/pwa-icon-192.png">
<link rel="icon" type="image/png" sizes="192x192" href="/pwa-icon-192.png">

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js')
            .then(function (reg) { console.log('SW registered:', reg.scope); })
            .catch(function (err) { console.warn('SW registration failed:', err); });
    });
}
</script>

<style>
/* Mobile UX improvements */
@media (max-width: 768px) {
    /* Larger touch targets */
    .fi-btn { min-height: 44px !important; padding: 10px 16px !important; }
    .fi-input { font-size: 16px !important; } /* prevents iOS zoom */
    .fi-ta-cell { padding: 10px 8px !important; }

    /* Sticky header on mobile */
    .fi-topbar { position: sticky; top: 0; z-index: 40; }

    /* Full-width cards on mobile */
    .fi-wi-stats-overview-stat { min-width: 0 !important; }
}

/* Install prompt banner */
#pwa-install-banner {
    display: none;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #1f2937;
    color: white;
    padding: 12px 16px;
    z-index: 9999;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    font-size: 13px;
}
#pwa-install-banner.show { display: flex; }
#pwa-install-banner button {
    background: #f59e0b;
    color: #1f2937;
    border: none;
    padding: 6px 14px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
#pwa-install-banner .dismiss {
    background: transparent;
    color: #9ca3af;
    font-size: 11px;
    padding: 4px 8px;
}
</style>
