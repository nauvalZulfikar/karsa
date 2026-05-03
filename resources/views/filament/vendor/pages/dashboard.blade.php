<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                Laporan Hari Ini — {{ now()->translatedFormat('l, d F Y') }}
            </h2>
            @php $laporanHariIni = $this->getLaporanHariIni(); @endphp
            @if($laporanHariIni->isEmpty())
                <p class="text-sm text-gray-400">Belum ada laporan hari ini.</p>
            @else
                <div class="space-y-2">
                    @foreach($laporanHariIni as $l)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $l->jenis === 'masuk' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $l->jenis === 'masuk' ? 'Masuk' : 'Pulang' }}
                        </span>
                        <span class="text-gray-600 dark:text-gray-300">{{ $l->pekerjaan->nama_pekerjaan }}</span>
                        <span class="text-gray-400 text-xs">{{ $l->submitted_at?->format('H:i') }} WIB</span>
                        <span class="text-xs px-1.5 py-0.5 rounded {{ $l->status === 'approved' ? 'bg-green-100 text-green-600' : ($l->status === 'rejected' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-600') }}">
                            {{ ucfirst($l->status) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Proyek Saya</h2>
            <div class="grid gap-3">
                @forelse($this->getPekerjaan() as $p)
                <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex justify-between items-start gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-800 dark:text-white text-sm truncate">{{ $p->nama_pekerjaan }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ $p->bidang?->nama }} &bull;
                                Deadline: {{ $p->tanggal_akhir?->format('d/m/Y') ?? '-' }}
                                @if($p->sisa_hari !== null)
                                    &bull; <span class="{{ $p->sisa_hari < 0 ? 'text-red-500' : ($p->sisa_hari <= 7 ? 'text-orange-500' : 'text-green-600') }}">
                                        {{ $p->sisa_hari < 0 ? abs($p->sisa_hari).' hari terlambat' : $p->sisa_hari.' hari lagi' }}
                                    </span>
                                @endif
                            </p>
                        </div>
                        <span class="flex-shrink-0 text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            {{ $p->statusPekerjaan?->nama ?? '-' }}
                        </span>
                    </div>
                    <div class="mt-2">
                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5">
                            <div class="bg-teal-500 h-1.5 rounded-full" style="width: {{ $p->progres_persen }}%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5 text-right">{{ $p->progres_persen }}%</p>
                    </div>
                </div>
                @empty
                <div class="p-6 text-center text-gray-400 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                    Tidak ada proyek yang ditugaskan.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
