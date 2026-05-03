<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex items-center gap-4">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Simpan Pengaturan
            </x-filament::button>

            <x-filament::button
                type="button"
                wire:click="mount"
                color="gray"
                icon="heroicon-o-arrow-path"
            >
                Reset ke Tersimpan
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
