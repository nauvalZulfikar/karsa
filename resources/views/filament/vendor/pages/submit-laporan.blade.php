<x-filament-panels::page>
    {{-- Offline notice --}}
    <div id="offline-notice" class="hidden mb-4 rounded-lg bg-red-50 border border-red-200 p-3 flex items-center gap-2 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">
        <span>📡</span>
        <span>Kamu sedang offline. Sambungkan internet sebelum mengirim laporan.</span>
    </div>

    <div class="max-w-lg mx-auto space-y-4">
        {{-- Live clock --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 text-center">
            <p class="text-4xl font-bold text-gray-800 dark:text-white font-mono tracking-widest" id="jam-sekarang">--:--:--</p>
            <p class="text-xs text-gray-400 mt-1">Waktu sekarang (WIB)</p>
            <div class="flex gap-2 justify-center mt-3 text-xs flex-wrap">
                <span class="px-3 py-1 bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 rounded-full font-medium">
                    🌅 Masuk: {{ \App\Models\SystemSetting::get('jam_masuk_buka','06:00') }} – {{ \App\Models\SystemSetting::get('jam_masuk_tutup','09:00') }}
                </span>
                <span class="px-3 py-1 bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 rounded-full font-medium">
                    🌇 Pulang: {{ \App\Models\SystemSetting::get('jam_pulang_buka','15:00') }} – {{ \App\Models\SystemSetting::get('jam_pulang_tutup','18:00') }}
                </span>
            </div>
        </div>

        {{-- GPS status badge --}}
        <div id="gps-status" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-xs text-gray-500">
            <span id="gps-icon">📍</span>
            <span id="gps-text">Mendeteksi lokasi GPS...</span>
        </div>

        {{-- Form --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <x-filament-panels::form wire:submit="submit">
                {{ $this->form }}
                <div class="mt-6">
                    <x-filament::button
                        type="submit"
                        size="lg"
                        class="w-full"
                        style="min-height:52px;font-size:16px;"
                        icon="heroicon-o-paper-airplane"
                    >
                        Kirim Laporan
                    </x-filament::button>
                </div>
            </x-filament-panels::form>
        </div>
    </div>

    <script>
        // Live clock
        function updateJam() {
            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            const el = document.getElementById('jam-sekarang');
            if (el) el.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
        }
        updateJam();
        setInterval(updateJam, 1000);

        // GPS capture
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(pos) {
                @this.set('data.latitude', pos.coords.latitude);
                @this.set('data.longitude', pos.coords.longitude);
                const txt = document.getElementById('gps-text');
                const ico = document.getElementById('gps-icon');
                if (txt) txt.textContent = 'Lokasi terdeteksi (' + pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5) + ')';
                if (ico) ico.textContent = '✅';
            }, function(err) {
                const txt = document.getElementById('gps-text');
                const ico = document.getElementById('gps-icon');
                if (txt) txt.textContent = 'Lokasi tidak tersedia (GPS diblokir)';
                if (ico) ico.textContent = '⚠️';
            }, { timeout: 10000, enableHighAccuracy: true });
        }

        // Offline detection
        function updateOnlineStatus() {
            const notice = document.getElementById('offline-notice');
            if (!notice) return;
            if (!navigator.onLine) {
                notice.classList.remove('hidden');
            } else {
                notice.classList.add('hidden');
            }
        }
        window.addEventListener('online',  updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus();
    </script>
</x-filament-panels::page>
