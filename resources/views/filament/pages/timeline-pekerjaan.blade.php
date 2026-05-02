<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Filters --}}
        <div class="flex gap-4 items-center p-4 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Bidang:</label>
                <select wire:model.live="filterBidang" class="text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="">Semua Bidang</option>
                    @foreach($this->getBidangOptions() as $id => $nama)
                        <option value="{{ $id }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tahun:</label>
                <select wire:model.live="filterTahun" class="text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="2025">2025</option>
                    <option value="2026" selected>2026</option>
                    <option value="2027">2027</option>
                </select>
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex gap-4 text-xs flex-wrap">
            @foreach([['aman','bg-green-500','Aman'],['waspada','bg-yellow-500','Waspada (≤14 hari)'],['kritis','bg-orange-500','Kritis (≤7 hari)'],['terlambat','bg-red-500','Terlambat'],['selesai','bg-blue-500','Selesai']] as [$key,$color,$label])
            <div class="flex items-center gap-1">
                <div class="w-3 h-3 rounded-full {{ $color }}"></div>
                <span class="text-gray-600 dark:text-gray-400">{{ $label }}</span>
            </div>
            @endforeach
        </div>

        {{-- Timeline Table --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-1/3">Pekerjaan</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-24">Bidang</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-24">Mulai</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-24">Akhir</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-24">Sisa</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Progress</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($this->getPekerjaan() as $p)
                    @php
                        $statusColor = match($p->status_waktu) {
                            'aman'      => 'bg-green-500',
                            'waspada'   => 'bg-yellow-500',
                            'kritis'    => 'bg-orange-500',
                            'terlambat' => 'bg-red-500',
                            'selesai'   => 'bg-blue-500',
                            default     => 'bg-gray-400',
                        };
                        $sisaHari = $p->sisa_hari;
                        $sisaLabel = $sisaHari === null ? '-' : ($sisaHari < 0 ? abs($sisaHari).' hari lewat' : $sisaHari.' hari');
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-2 h-2 rounded-full {{ $statusColor }} flex-shrink-0"></div>
                                <a href="{{ \App\Filament\Resources\PekerjaanResource::getUrl('view', ['record' => $p]) }}"
                                   class="text-primary-600 hover:underline line-clamp-2">
                                    {{ $p->nama_pekerjaan }}
                                </a>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ $p->bidang?->kode }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs">{{ $p->tanggal_mulai?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300 text-xs">{{ $p->tanggal_akhir?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-xs font-medium {{ $sisaHari !== null && $sisaHari < 0 ? 'text-red-600' : ($sisaHari <= 7 ? 'text-orange-600' : 'text-gray-600 dark:text-gray-300') }}">
                            {{ $sisaLabel }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 dark:bg-gray-600 rounded-full h-2 max-w-24">
                                    <div class="{{ $statusColor }} h-2 rounded-full" style="width: {{ $p->progres_persen }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400 w-8">{{ $p->progres_persen }}%</span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">Tidak ada pekerjaan ditemukan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
