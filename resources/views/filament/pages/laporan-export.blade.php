<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Filter Laporan</x-slot>
            <x-slot name="description">Pilih filter sebelum mengunduh laporan</x-slot>
            {{ $this->form }}
        </x-filament::section>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            {{-- Rekap Pekerjaan --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-briefcase class="h-5 w-5 text-blue-500" />
                        Rekap Pekerjaan
                    </div>
                </x-slot>
                <x-slot name="description">Semua proyek sesuai filter bidang & tahun</x-slot>

                <div class="flex gap-3 mt-2">
                    <x-filament::button
                        wire:click="exportPekerjaanExcel"
                        color="success"
                        icon="heroicon-o-table-cells"
                        size="sm"
                    >
                        Excel (.xlsx)
                    </x-filament::button>

                    <x-filament::button
                        wire:click="exportPekerjaanPdf"
                        color="danger"
                        icon="heroicon-o-document-text"
                        size="sm"
                    >
                        PDF
                    </x-filament::button>
                </div>
            </x-filament::section>

            {{-- Laporan Harian Vendor --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-camera class="h-5 w-5 text-green-500" />
                        Laporan Harian Vendor
                    </div>
                </x-slot>
                <x-slot name="description">Rekap laporan masuk/pulang sesuai rentang tanggal</x-slot>

                <div class="flex gap-3 mt-2">
                    <x-filament::button
                        wire:click="exportLaporanExcel"
                        color="success"
                        icon="heroicon-o-table-cells"
                        size="sm"
                    >
                        Excel (.xlsx)
                    </x-filament::button>
                </div>
            </x-filament::section>

            {{-- Rekap Pengadaan --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-shopping-cart class="h-5 w-5 text-yellow-500" />
                        Rekap Pengadaan Barang
                    </div>
                </x-slot>
                <x-slot name="description">Rencana vs realisasi pengadaan per proyek</x-slot>

                <div class="flex gap-3 mt-2">
                    <x-filament::button
                        wire:click="exportPengadaanExcel"
                        color="success"
                        icon="heroicon-o-table-cells"
                        size="sm"
                    >
                        Excel (.xlsx)
                    </x-filament::button>
                </div>
            </x-filament::section>

            {{-- Rekap Termin Pembayaran --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-banknotes class="h-5 w-5 text-purple-500" />
                        Rekap Termin Pembayaran
                    </div>
                </x-slot>
                <x-slot name="description">Status pembayaran termin per proyek</x-slot>

                <div class="flex gap-3 mt-2">
                    <x-filament::button
                        wire:click="exportTerminExcel"
                        color="success"
                        icon="heroicon-o-table-cells"
                        size="sm"
                    >
                        Excel (.xlsx)
                    </x-filament::button>
                </div>
            </x-filament::section>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
