<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Template Download --}}
        <x-filament::section>
            <x-slot name="heading">Download Template</x-slot>
            <x-slot name="description">Download template Excel sebelum mengisi data agar format kolom sesuai</x-slot>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-medium">Template Pekerjaan</p>
                    <p class="mt-1 text-xs text-gray-500">Kolom: bidang, nama_pekerjaan, perusahaan, tahun_anggaran, nilai_pagu, nilai_kontrak, no_spk, tanggal_mulai, tanggal_akhir, hari_kerja, progres_persen</p>
                    <div class="mt-3">
                        <x-filament::button
                            tag="a"
                            href="{{ route('import.template', 'pekerjaan') }}"
                            size="sm"
                            color="gray"
                            icon="heroicon-o-arrow-down-tray"
                        >
                            Unduh Template
                        </x-filament::button>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-medium">Template Perusahaan</p>
                    <p class="mt-1 text-xs text-gray-500">Kolom: nama, npwp, alamat, no_telp, email, direktur, blacklist</p>
                    <div class="mt-3">
                        <x-filament::button
                            tag="a"
                            href="{{ route('import.template', 'perusahaan') }}"
                            size="sm"
                            color="gray"
                            icon="heroicon-o-arrow-down-tray"
                        >
                            Unduh Template
                        </x-filament::button>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <p class="font-medium">Template Hari Libur</p>
                    <p class="mt-1 text-xs text-gray-500">Kolom: tanggal (dd/mm/yyyy), nama</p>
                    <div class="mt-3">
                        <x-filament::button
                            tag="a"
                            href="{{ route('import.template', 'hari-libur') }}"
                            size="sm"
                            color="gray"
                            icon="heroicon-o-arrow-down-tray"
                        >
                            Unduh Template
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Upload Form --}}
        <x-filament::section>
            <x-slot name="heading">Upload File Excel</x-slot>
            <x-slot name="description">Pilih satu atau lebih file untuk diimport sekaligus</x-slot>

            <form wire:submit="import">
                {{ $this->form }}

                <div class="mt-4">
                    <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                        Proses Import
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Import Results --}}
        @if($importResult)
        <x-filament::section>
            <x-slot name="heading">Hasil Import</x-slot>

            <div class="space-y-4">
                @foreach($importResult as $type => $result)
                <div class="rounded-lg border p-4 {{ count($result['errors']) > 0 ? 'border-yellow-300 bg-yellow-50 dark:bg-yellow-900/20' : 'border-green-300 bg-green-50 dark:bg-green-900/20' }}">
                    <p class="font-semibold capitalize">{{ str_replace('_', ' ', $type) }}</p>
                    <p class="mt-1 text-sm">
                        ✓ {{ $result['imported'] }} baris berhasil diimport
                        @if($result['skipped'] > 0)
                            &nbsp;|&nbsp; ⚠ {{ $result['skipped'] }} baris dilewati
                        @endif
                    </p>

                    @if(count($result['errors']) > 0)
                    <div class="mt-2">
                        <p class="text-xs font-medium text-yellow-700 dark:text-yellow-400">Detail error:</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5 text-xs text-yellow-600 dark:text-yellow-300">
                            @foreach($result['errors'] as $err)
                            <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </x-filament::section>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
