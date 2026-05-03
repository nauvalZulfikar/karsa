<x-filament-widgets::widget>
    <style>
        .kanban-root {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            position: relative;
        }
        .dark .kanban-root {
            background: #1f2937;
            border-color: #374151;
        }
        .kanban-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .kanban-title {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
        }
        .dark .kanban-title { color: #f3f4f6; }
        .kanban-filters {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        .kanban-filter-btn {
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 9999px;
            background: #f3f4f6;
            color: #374151;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
            font-weight: 500;
        }
        .kanban-filter-btn:hover { background: #fef3c7; }
        .kanban-filter-btn-active {
            background: #f59e0b;
            color: #fff;
        }
        .kanban-filter-btn-active:hover { background: #d97706; }
        .dark .kanban-filter-btn { background: #374151; color: #e5e7eb; }
        .dark .kanban-filter-btn:hover { background: #4b5563; }
        .kanban-board {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        .kanban-board::-webkit-scrollbar { height: 8px; }
        .kanban-board::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        .kanban-col {
            flex: 0 0 280px;
            background: #f9fafb;
            border-radius: 14px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            max-height: 600px;
        }
        .dark .kanban-col { background: #111827; }
        .kanban-col-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid;
        }
        .kanban-col-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }
        .dark .kanban-col-label { color: #e5e7eb; }
        .kanban-col-count {
            font-size: 11px;
            font-weight: 600;
            background: rgba(0,0,0,0.07);
            padding: 2px 8px;
            border-radius: 10px;
            color: #4b5563;
        }
        .dark .kanban-col-count { background: rgba(255,255,255,0.1); color: #d1d5db; }
        .kanban-cards {
            display: flex;
            flex-direction: column;
            gap: 8px;
            overflow-y: auto;
            flex: 1;
        }
        .kanban-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 12px;
            cursor: pointer;
            transition: all 0.15s;
            display: block;
            text-align: left;
            width: 100%;
        }
        .kanban-card:hover {
            border-color: #f59e0b;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }
        .dark .kanban-card { background: #1f2937; border-color: #4b5563; }
        .kanban-card-title {
            font-size: 13px;
            font-weight: 600;
            color: #1f2937;
            line-height: 1.3;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .dark .kanban-card-title { color: #f3f4f6; }
        .kanban-card-meta {
            font-size: 11px;
            color: #6b7280;
            display: flex;
            flex-wrap: wrap;
            gap: 4px 8px;
            margin-bottom: 8px;
        }
        .dark .kanban-card-meta { color: #9ca3af; }
        .kanban-card-progress {
            background: #e5e7eb;
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .dark .kanban-card-progress { background: #374151; }
        .kanban-card-progress-bar { height: 100%; border-radius: 3px; }
        .kanban-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
        }
        .kanban-card-progres-label { color: #6b7280; font-weight: 600; }
        .dark .kanban-card-progres-label { color: #d1d5db; }
        .kanban-card-days { font-weight: 600; color: #ef4444; }
        .kanban-card-days-ok { color: #10b981; }
        .kanban-empty {
            text-align: center;
            font-size: 12px;
            color: #9ca3af;
            padding: 20px 0;
        }

        /* Modal styles */
        .kanban-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 16px;
        }
        .kanban-modal {
            background: #fff;
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .dark .kanban-modal { background: #1f2937; color: #f3f4f6; }
        .kanban-modal-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid #e5e7eb;
            position: sticky;
            top: 0;
            background: #fff;
            border-radius: 20px 20px 0 0;
        }
        .dark .kanban-modal-header { background: #1f2937; border-color: #374151; }
        .kanban-modal-title {
            font-size: 17px;
            font-weight: 700;
            color: #1f2937;
            line-height: 1.3;
            margin: 0 0 8px 0;
        }
        .dark .kanban-modal-title { color: #f3f4f6; }
        .kanban-modal-status {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 9999px;
            color: #fff;
        }
        .kanban-modal-close {
            position: absolute;
            top: 16px;
            right: 18px;
            background: #f3f4f6;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            color: #6b7280;
        }
        .kanban-modal-close:hover { background: #e5e7eb; }
        .dark .kanban-modal-close { background: #374151; color: #d1d5db; }
        .kanban-modal-body { padding: 20px 24px; }
        .kanban-modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 16px;
            margin-bottom: 16px;
        }
        .kanban-modal-field { display: flex; flex-direction: column; gap: 2px; }
        .kanban-modal-label {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .dark .kanban-modal-label { color: #9ca3af; }
        .kanban-modal-value {
            font-size: 13px;
            color: #1f2937;
            font-weight: 500;
        }
        .dark .kanban-modal-value { color: #f3f4f6; }
        .kanban-modal-progress {
            background: #e5e7eb;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin: 4px 0 16px;
        }
        .dark .kanban-modal-progress { background: #374151; }
        .kanban-modal-section-title {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            margin: 16px 0 8px;
        }
        .dark .kanban-modal-section-title { color: #9ca3af; }
        .kanban-modal-counts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .kanban-modal-count-card {
            background: #f9fafb;
            border-radius: 10px;
            padding: 10px 12px;
            text-align: center;
        }
        .dark .kanban-modal-count-card { background: #111827; }
        .kanban-modal-count-num {
            font-size: 20px;
            font-weight: 700;
            color: #f59e0b;
        }
        .kanban-modal-count-label {
            font-size: 11px;
            color: #6b7280;
        }
        .dark .kanban-modal-count-label { color: #9ca3af; }
        .kanban-modal-actions {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            position: sticky;
            bottom: 0;
            background: #fff;
            border-radius: 0 0 20px 20px;
        }
        .dark .kanban-modal-actions { background: #1f2937; border-color: #374151; }
        .kanban-modal-btn {
            font-size: 13px;
            padding: 8px 18px;
            border-radius: 9999px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.15s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .kanban-modal-btn-primary { background: #f59e0b; color: #fff; }
        .kanban-modal-btn-primary:hover { background: #d97706; }
        .kanban-modal-btn-secondary { background: #f3f4f6; color: #374151; }
        .kanban-modal-btn-secondary:hover { background: #e5e7eb; }
        .dark .kanban-modal-btn-secondary { background: #374151; color: #e5e7eb; }
    </style>

    <div class="kanban-root"
         x-data="{ activeCard: null, openCard(c) { this.activeCard = c; } }">

        <div class="kanban-header">
            <div class="kanban-title">
                📋 Papan Pekerjaan {{ date('Y') }}
                @if ($filterBidangId)
                    @php $b = $bidangList->firstWhere('id', $filterBidangId); @endphp
                    @if ($b) <span style="color:#f59e0b;">— {{ $b->nama }}</span> @endif
                @endif
            </div>
            <div style="font-size: 12px; color: #6b7280;">Klik card untuk lihat detail</div>
        </div>

        {{-- Bidang filter buttons --}}
        <div class="kanban-filters">
            <button type="button" wire:click="setFilter(null)"
                class="kanban-filter-btn {{ $filterBidangId === null ? 'kanban-filter-btn-active' : '' }}">
                🌐 Semua
            </button>
            @foreach ($bidangList as $b)
                <button type="button" wire:click="setFilter({{ $b->id }})"
                    class="kanban-filter-btn {{ $filterBidangId === $b->id ? 'kanban-filter-btn-active' : '' }}">
                    {{ $b->nama }}
                </button>
            @endforeach
        </div>

        <div class="kanban-board">
            @foreach ($columns as $key => $col)
                <div class="kanban-col">
                    <div class="kanban-col-header" style="border-bottom-color: {{ $col['color'] }};">
                        <span class="kanban-col-label">{{ $col['label'] }}</span>
                        <span class="kanban-col-count">{{ count($col['items']) }}</span>
                    </div>
                    <div class="kanban-cards">
                        @forelse ($col['items'] as $item)
                            <button type="button" class="kanban-card"
                                @click='openCard(@json($item))'>
                                <div class="kanban-card-title">{{ $item['nama'] }}</div>
                                <div class="kanban-card-meta">
                                    @if ($item['vendor'])
                                        <span>🏢 {{ \Illuminate\Support\Str::limit($item['vendor'], 25) }}</span>
                                    @endif
                                    @if ($item['bidang'])
                                        <span>· {{ $item['bidang'] }}</span>
                                    @endif
                                </div>
                                <div class="kanban-card-progress">
                                    <div class="kanban-card-progress-bar"
                                         style="width: {{ $item['progres'] }}%; background: {{ $col['color'] }};"></div>
                                </div>
                                <div class="kanban-card-footer">
                                    <span class="kanban-card-progres-label">{{ number_format($item['progres'], 0) }}%</span>
                                    @if ($item['sisa_hari'] !== null)
                                        @if ($item['sisa_hari'] < 0)
                                            <span class="kanban-card-days">⚠️ Lewat {{ abs($item['sisa_hari']) }} hari</span>
                                        @elseif ($item['sisa_hari'] <= 7)
                                            <span class="kanban-card-days">⏰ {{ $item['sisa_hari'] }} hari lagi</span>
                                        @else
                                            <span class="kanban-card-days kanban-card-days-ok">{{ $item['sisa_hari'] }} hari</span>
                                        @endif
                                    @endif
                                </div>
                            </button>
                        @empty
                            <div class="kanban-empty">— Kosong —</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Floating Modal --}}
        <div x-show="activeCard !== null"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click.self="activeCard = null"
             @keydown.escape.window="activeCard = null"
             class="kanban-modal-overlay"
             style="display: none;">

            <div class="kanban-modal" @click.stop x-show="activeCard !== null"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">

                <div class="kanban-modal-header" style="position: relative;">
                    <button type="button" class="kanban-modal-close" @click="activeCard = null">×</button>
                    <h3 class="kanban-modal-title" x-text="activeCard?.nama"></h3>
                    <span class="kanban-modal-status"
                          :style="`background: ${activeCard?.col_color}`"
                          x-text="activeCard?.status_label || '-'"></span>
                </div>

                <div class="kanban-modal-body">
                    {{-- Counts --}}
                    <div class="kanban-modal-counts">
                        <div class="kanban-modal-count-card">
                            <div class="kanban-modal-count-num" x-text="activeCard?.jumlah_personil ?? 0"></div>
                            <div class="kanban-modal-count-label">Personil</div>
                        </div>
                        <div class="kanban-modal-count-card">
                            <div class="kanban-modal-count-num" x-text="activeCard?.jumlah_termin ?? 0"></div>
                            <div class="kanban-modal-count-label">Termin</div>
                        </div>
                        <div class="kanban-modal-count-card">
                            <div class="kanban-modal-count-num" x-text="activeCard?.jumlah_milestone ?? 0"></div>
                            <div class="kanban-modal-count-label">Milestone</div>
                        </div>
                    </div>

                    {{-- Progress --}}
                    <div class="kanban-modal-label">Progres</div>
                    <div class="kanban-modal-progress">
                        <div class="kanban-card-progress-bar"
                             :style="`width: ${activeCard?.progres ?? 0}%; background: ${activeCard?.col_color}`"></div>
                    </div>
                    <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px;"
                         x-text="`${activeCard?.progres ?? 0}%`"></div>

                    {{-- Field grid --}}
                    <div class="kanban-modal-grid">
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">Bidang</span>
                            <span class="kanban-modal-value" x-text="activeCard?.bidang || '-'"></span>
                        </div>
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">Vendor</span>
                            <span class="kanban-modal-value" x-text="activeCard?.vendor || '-'"></span>
                        </div>
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">No SPK</span>
                            <span class="kanban-modal-value" x-text="activeCard?.no_spk || '-'" style="font-size:11px;"></span>
                        </div>
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">No SPMK</span>
                            <span class="kanban-modal-value" x-text="activeCard?.no_spmk || '-'" style="font-size:11px;"></span>
                        </div>
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">Nilai Pagu</span>
                            <span class="kanban-modal-value" x-text="activeCard?.nilai_pagu || '-'"></span>
                        </div>
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">Nilai Kontrak</span>
                            <span class="kanban-modal-value" x-text="activeCard?.nilai_kontrak || '-'"></span>
                        </div>
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">Tanggal Mulai</span>
                            <span class="kanban-modal-value" x-text="activeCard?.tanggal_mulai || '-'"></span>
                        </div>
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">Tanggal Akhir</span>
                            <span class="kanban-modal-value" x-text="activeCard?.tanggal_akhir || '-'"></span>
                        </div>
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">Hari Kerja</span>
                            <span class="kanban-modal-value"
                                  x-text="activeCard?.hari_kerja ? `${activeCard.hari_kerja} hari` : '-'"></span>
                        </div>
                        <div class="kanban-modal-field">
                            <span class="kanban-modal-label">Sisa Hari</span>
                            <span class="kanban-modal-value">
                                <template x-if="activeCard?.sisa_hari === null || activeCard?.sisa_hari === undefined">
                                    <span>-</span>
                                </template>
                                <template x-if="activeCard?.sisa_hari < 0">
                                    <span style="color:#dc2626;">⚠ Lewat <span x-text="Math.abs(activeCard?.sisa_hari)"></span> hari</span>
                                </template>
                                <template x-if="activeCard?.sisa_hari >= 0">
                                    <span x-text="`${activeCard?.sisa_hari} hari`"></span>
                                </template>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="kanban-modal-actions">
                    <button type="button" class="kanban-modal-btn kanban-modal-btn-secondary"
                            @click="activeCard = null">Tutup</button>
                    <a :href="activeCard?.url_edit" class="kanban-modal-btn kanban-modal-btn-secondary">✏️ Edit</a>
                    <a :href="activeCard?.url_detail" class="kanban-modal-btn kanban-modal-btn-primary">📂 Buka Detail Lengkap</a>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
