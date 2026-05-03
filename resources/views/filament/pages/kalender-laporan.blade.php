<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-4 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Proyek:</label>
                <select wire:model.live="filterPekerjaan"
                        class="text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white min-w-64">
                    <option value="">-- Pilih Proyek --</option>
                    @foreach($this->getPekerjaanOptions() as $id => $nama)
                        <option value="{{ $id }}">{{ Str::limit($nama, 55) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Minggu:</label>
                <input type="date" wire:model.live="filterTanggal" value="{{ $filterTanggal }}"
                       class="text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
        </div>

        <div class="flex gap-4 text-xs flex-wrap">
            <div class="flex items-center gap-1.5"><div class="w-4 h-4 rounded-full bg-green-500 flex items-center justify-center text-white text-xs">M</div><span class="text-gray-600 dark:text-gray-400">Masuk ✓</span></div>
            <div class="flex items-center gap-1.5"><div class="w-4 h-4 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs">P</div><span class="text-gray-600 dark:text-gray-400">Pulang ✓</span></div>
            <div class="flex items-center gap-1.5"><div class="w-4 h-4 rounded-full bg-red-200 flex items-center justify-center text-red-400 text-xs">✗</div><span class="text-gray-600 dark:text-gray-400">Belum lapor</span></div>
        </div>

        @php $data = $this->getKalenderData(); @endphp

        @if(empty($data))
            <div class="p-10 text-center text-gray-400 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                Pilih proyek untuk melihat kalender laporan.
            </div>
        @elseif(empty($data['rows']))
            <div class="p-10 text-center text-gray-400 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                Tidak ada vendor yang ditugaskan ke proyek ini.
            </div>
        @else
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300 w-48">Vendor</th>
                        @foreach($data['dates'] as $d)
                        <th class="px-2 py-3 text-center font-medium text-gray-600 dark:text-gray-300 w-20 text-xs">{{ $d }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($data['rows'] as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-200 text-xs">{{ $row['vendor'] }}</td>
                        @foreach($row['cols'] as $col)
                        <td class="px-2 py-3 text-center {{ $col['weekend'] ? 'bg-gray-50 dark:bg-gray-700/30' : '' }}">
                            <div class="flex gap-1 justify-center">
                                <span title="Masuk"
                                      class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold
                                             {{ $col['masuk'] ? 'bg-green-500 text-white' : 'bg-red-100 text-red-300 dark:bg-red-900/30' }}">
                                    M
                                </span>
                                <span title="Pulang"
                                      class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold
                                             {{ $col['pulang'] ? 'bg-blue-500 text-white' : 'bg-red-100 text-red-300 dark:bg-red-900/30' }}">
                                    P
                                </span>
                            </div>
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</x-filament-panels::page>
