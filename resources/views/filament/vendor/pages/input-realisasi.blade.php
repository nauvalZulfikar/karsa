<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Form Input Realisasi Pengadaan</x-slot>
            <x-slot name="description">Laporkan pembelian dan pemakaian material di lapangan</x-slot>

            <form wire:submit="submit">
                {{ $this->form }}

                <div class="mt-6">
                    <x-filament::button type="submit" size="lg">
                        Kirim Laporan Pengadaan
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Riwayat Laporan Saya</x-slot>

            {{ $this->table }}
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
