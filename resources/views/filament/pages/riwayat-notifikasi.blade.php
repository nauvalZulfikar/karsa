<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">Status Konfigurasi</x-slot>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Provider</p>
                    <p class="mt-1 text-lg font-semibold">{{ strtoupper(config('services.wa_gateway.provider', '-')) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Token API</p>
                    <p class="mt-1 text-lg font-semibold">
                        @if(config('services.wa_gateway.token'))
                            <span class="text-green-600">✓ Terkonfigurasi</span>
                        @else
                            <span class="text-red-500">✗ Belum diset</span>
                        @endif
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Alert Deadline (hari ke-)</p>
                    <p class="mt-1 text-lg font-semibold">{{ env('NOTIF_DEADLINE_DAYS', '14,7,3') }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Jadwal Notifikasi Otomatis</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b dark:border-gray-700">
                            <th class="pb-2 text-left font-medium text-gray-600 dark:text-gray-400">Command</th>
                            <th class="pb-2 text-left font-medium text-gray-600 dark:text-gray-400">Jadwal</th>
                            <th class="pb-2 text-left font-medium text-gray-600 dark:text-gray-400">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-700">
                        <tr>
                            <td class="py-2 font-mono text-xs">notifikasi:deadline</td>
                            <td class="py-2">Setiap hari 07:00</td>
                            <td class="py-2">Peringatan deadline H-14, H-7, H-3 ke personil proyek</td>
                        </tr>
                        <tr>
                            <td class="py-2 font-mono text-xs">notifikasi:laporan-harian masuk</td>
                            <td class="py-2">Senin–Jumat 06:30</td>
                            <td class="py-2">Reminder laporan masuk ke vendor yang belum submit</td>
                        </tr>
                        <tr>
                            <td class="py-2 font-mono text-xs">notifikasi:laporan-harian pulang</td>
                            <td class="py-2">Senin–Jumat 15:00</td>
                            <td class="py-2">Reminder laporan pulang ke vendor yang belum submit</td>
                        </tr>
                        <tr>
                            <td class="py-2 font-mono text-xs">notifikasi:termin-pending</td>
                            <td class="py-2">Setiap hari 08:00</td>
                            <td class="py-2">WA ke PPK untuk termin yang belum disetujui lebih dari 1 hari</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Cara Mengaktifkan</x-slot>
            <div class="text-sm space-y-2">
                <p><strong>1.</strong> Daftar akun di <strong>Fonnte</strong> (fonnte.com) atau Wablas</p>
                <p><strong>2.</strong> Salin token API dan isi di file <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">.env</code>:</p>
                <pre class="rounded bg-gray-100 p-2 text-xs dark:bg-gray-800">WA_GATEWAY_TOKEN=token_anda_disini</pre>
                <p><strong>3.</strong> Aktifkan Laravel Scheduler di server (crontab):</p>
                <pre class="rounded bg-gray-100 p-2 text-xs dark:bg-gray-800">* * * * * cd /path/project && php artisan schedule:run >> /dev/null 2>&1</pre>
                <p><strong>4.</strong> Isi nomor HP (format 08xx) di profil setiap user dan tenaga ahli</p>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
