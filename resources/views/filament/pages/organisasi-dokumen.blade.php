<x-filament-panels::page>
    @php($items = $this->unorganized)

    @if ($items->isEmpty())
        <x-filament::section>
            <div class="py-10 text-center text-gray-500">
                <x-filament::icon icon="heroicon-o-check-circle" class="mx-auto mb-3 h-10 w-10 text-success-500" />
                <p class="font-medium">Semua file sudah diorganise.</p>
                <p class="mt-1 text-sm">File yang diupload lewat chat AI akan muncul di sini untuk dipindahkan ke proyek.</p>
            </div>
        </x-filament::section>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
            {{-- ── KIRI: daftar file belum diorganise ── --}}
            <div class="lg:col-span-4">
                <x-filament::section>
                    <x-slot name="heading">Belum Diorganise ({{ $items->count() }})</x-slot>

                    <div class="-mx-2 max-h-[70vh] space-y-1 overflow-y-auto">
                        @foreach ($items as $u)
                            @php($active = $selectedId === $u->id)
                            <button type="button" wire:click="select({{ $u->id }})" wire:key="up-{{ $u->id }}"
                                @class([
                                    'flex w-full items-start gap-2 rounded-lg px-2 py-2 text-left text-sm transition',
                                    'bg-primary-50 ring-1 ring-primary-500 dark:bg-primary-500/10' => $active,
                                    'hover:bg-gray-50 dark:hover:bg-white/5' => ! $active,
                                ])>
                                <x-filament::icon
                                    :icon="match ($u->kind()) {
                                        'pdf' => 'heroicon-o-document-text',
                                        'image' => 'heroicon-o-photo',
                                        'sheet' => 'heroicon-o-table-cells',
                                        'word' => 'heroicon-o-document',
                                        default => 'heroicon-o-paper-clip',
                                    }"
                                    class="mt-0.5 h-5 w-5 shrink-0 text-gray-400" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate font-medium text-gray-800 dark:text-gray-200">{{ $u->original_name }}</span>
                                    <span class="block text-xs text-gray-400">
                                        {{ strtoupper($u->kind()) }} · {{ number_format(($u->size_bytes ?? 0) / 1024, 0) }} KB
                                        @if ($u->is_scanned_pdf) · scan @endif
                                    </span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </x-filament::section>
            </div>

            {{-- ── KANAN: viewer + form organise ── --}}
            <div class="lg:col-span-8">
                @if ($this->selected)
                    <div class="space-y-4">
                        <x-filament::section>
                            <x-slot name="heading">
                                <span class="break-all">{{ $this->selected->original_name }}</span>
                            </x-slot>
                            <x-slot name="headerEnd">
                                <x-filament::button tag="a" href="{{ route('chat-upload.preview', $this->selected->id) }}"
                                    target="_blank" size="sm" color="gray" icon="heroicon-o-arrow-top-right-on-square">
                                    Tab baru
                                </x-filament::button>
                            </x-slot>

                            <iframe
                                src="{{ route('chat-upload.preview', $this->selected->id) }}"
                                wire:key="viewer-{{ $this->selected->id }}"
                                class="h-[60vh] w-full rounded-lg border border-gray-200 bg-white dark:border-gray-700"
                                title="Preview {{ $this->selected->original_name }}"></iframe>
                        </x-filament::section>

                        <x-filament::section>
                            <x-slot name="heading">Masukkan ke Proyek</x-slot>

                            <form wire:submit="organize" class="space-y-4">
                                {{ $this->form }}

                                <div class="flex justify-end">
                                    <x-filament::button type="submit" icon="heroicon-o-folder-arrow-down">
                                        Simpan ke Dokumen Proyek
                                    </x-filament::button>
                                </div>
                            </form>
                        </x-filament::section>
                    </div>
                @else
                    <x-filament::section>
                        <div class="py-16 text-center text-gray-400">
                            <x-filament::icon icon="heroicon-o-cursor-arrow-rays" class="mx-auto mb-3 h-8 w-8" />
                            <p>Pilih file di sebelah kiri untuk melihat isinya & memasukkannya ke proyek.</p>
                        </div>
                    </x-filament::section>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
