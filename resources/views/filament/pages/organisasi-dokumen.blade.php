<x-filament-panels::page>
    @php($items = $this->unorganized)
    @php($recs = $this->recommendations)
    @php($group = $this->selectedGroup)

    @if ($items->isEmpty())
        <x-filament::section>
            <div class="py-10 text-center text-gray-500">
                <x-filament::icon icon="heroicon-o-check-circle" class="mx-auto mb-3 h-10 w-10 text-success-500" />
                <p class="font-medium">Semua file sudah diorganise.</p>
                <p class="mt-1 text-sm">File yang diupload lewat chat AI akan muncul di sini untuk dipindahkan ke proyek.</p>
            </div>
        </x-filament::section>
    @else
        {{-- ── REKOMENDASI GRUP ── --}}
        @if (! empty($recs))
            <x-filament::section
                icon="heroicon-o-sparkles"
                icon-color="warning"
                collapsible
                :collapsed="(bool) $group">
                <x-slot name="heading">Rekomendasi Grup ({{ count($recs) }})</x-slot>
                <x-slot name="description">File yang sinyal namanya menunjuk ke satu paket pekerjaan — bisa diorganise sekaligus.</x-slot>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($recs as $rec)
                        @php($isSel = $selectedGroupKey === $rec['key'])
                        <div @class([
                            'rounded-xl border p-3 transition',
                            'border-warning-400 ring-1 ring-warning-400 dark:border-warning-500' => $isSel,
                            'border-gray-200 dark:border-gray-700' => ! $isSel,
                        ])>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $rec['label'] }}</span>
                                <x-filament::badge color="warning">{{ $rec['files']->count() }} file</x-filament::badge>
                            </div>
                            <ul class="mt-2 space-y-0.5 text-xs text-gray-500">
                                @foreach ($rec['files'] as $f)
                                    <li class="truncate">• {{ $f->original_name }}</li>
                                @endforeach
                            </ul>
                            <div class="mt-3">
                                <x-filament::button size="xs" color="warning" wire:click="selectGroup('{{ $rec['key'] }}')">
                                    Organise grup ini
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

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

            {{-- ── KANAN: viewer + form ── --}}
            <div class="lg:col-span-8">
                @if ($this->selected)
                    <div class="space-y-4">
                        <x-filament::section>
                            <x-slot name="heading"><span class="break-all">{{ $this->selected->original_name }}</span></x-slot>
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

                        @if ($group)
                            {{-- ── MODE GRUP: bulk organise ── --}}
                            <x-filament::section icon="heroicon-o-rectangle-stack" icon-color="warning">
                                <x-slot name="heading">Organise Grup: {{ $group['label'] }}</x-slot>
                                <x-slot name="description">{{ $group['files']->count() }} file akan dimasukkan ke satu proyek (tipe ditebak otomatis dari nama).</x-slot>

                                <div class="space-y-3">
                                    <ul class="space-y-1 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5">
                                        @foreach ($group['files'] as $gf)
                                            <li class="flex items-center justify-between gap-2">
                                                <button type="button" wire:click="select({{ $gf->id }})"
                                                    class="truncate text-left hover:underline {{ $selectedId === $gf->id ? 'font-semibold text-primary-600' : 'text-gray-700 dark:text-gray-300' }}">
                                                    {{ $gf->original_name }}
                                                </button>
                                                <x-filament::badge color="gray">{{ \App\Models\Dokumen::$tipeOptions[$this->guessTipe($gf->original_name)] ?? '—' }}</x-filament::badge>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Proyek Tujuan <span class="text-danger-500">*</span></label>
                                        <select wire:model="groupPekerjaanId"
                                            class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                                            <option value="">— pilih proyek —</option>
                                            @foreach ($this->pekerjaanOptions() as $id => $nama)
                                                <option value="{{ $id }}">{{ $nama }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="flex justify-end gap-2">
                                        <x-filament::button color="gray" wire:click="clearGroup">Batal</x-filament::button>
                                        <x-filament::button wire:click="organizeGroup" icon="heroicon-o-folder-arrow-down"
                                            wire:loading.attr="disabled" wire:target="organizeGroup">
                                            Simpan {{ $group['files']->count() }} File
                                        </x-filament::button>
                                    </div>
                                </div>
                            </x-filament::section>
                        @else
                            {{-- ── MODE SATUAN ── --}}
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
                        @endif
                    </div>
                @else
                    <x-filament::section>
                        <div class="py-16 text-center text-gray-400">
                            <x-filament::icon icon="heroicon-o-cursor-arrow-rays" class="mx-auto mb-3 h-8 w-8" />
                            <p>Pilih file di kiri, atau pilih <span class="font-medium text-warning-600">Rekomendasi Grup</span> di atas untuk organise sekaligus.</p>
                        </div>
                    </x-filament::section>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
