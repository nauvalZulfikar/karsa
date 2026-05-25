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

        /* Dark mode variables for modal */
        .kanban-root {
            --km-bg: #ffffff;
            --km-text: #1f2937;
            --km-muted: #6b7280;
            --km-border: #e5e7eb;
            --km-card-bg: #f9fafb;
            --km-count-bg: #f9fafb;
        }
        .dark .kanban-root {
            --km-bg: #1f2937;
            --km-text: #f3f4f6;
            --km-muted: #9ca3af;
            --km-border: #374151;
            --km-card-bg: #111827;
            --km-count-bg: #111827;
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
            background: var(--km-bg);
            color: var(--km-text);
            border-radius: 20px;
            max-width: 640px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .kanban-modal-header {
            padding: 24px 40px 16px;
            border-bottom: 1px solid var(--km-border);
            position: sticky;
            top: 0;
            background: var(--km-bg);
            border-radius: 20px 20px 0 0;
        }
        .kanban-modal-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--km-text);
            line-height: 1.3;
            margin: 0 0 8px 0;
        }
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
            background: var(--km-card-bg);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            color: var(--km-muted);
        }
        .kanban-modal-close:hover { opacity: 0.7; }
        .kanban-modal-body { padding: 24px 40px; }
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
            color: var(--km-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .kanban-modal-value {
            font-size: 13px;
            color: var(--km-text);
            font-weight: 500;
        }
        .kanban-modal-progress {
            background: var(--km-border);
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
            margin: 4px 0 0;
        }
        .kanban-modal-counts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .kanban-modal-count-card {
            background: var(--km-count-bg);
            border-radius: 10px;
            padding: 10px 12px;
            text-align: center;
        }
        .kanban-modal-count-num {
            font-size: 20px;
            font-weight: 700;
            color: #f59e0b;
        }
        .kanban-modal-count-label {
            font-size: 11px;
            color: var(--km-muted);
        }
        .kanban-modal-actions {
            padding: 16px 40px;
            border-top: 1px solid var(--km-border);
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            position: sticky;
            bottom: 0;
            background: var(--km-bg);
            border-radius: 0 0 20px 20px;
        }
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
        .kanban-modal-btn-secondary { background: var(--km-card-bg); color: var(--km-text); }
        .kanban-modal-btn-secondary:hover { opacity: 0.8; }
    </style>

    <div class="kanban-root"
         x-data="{ activeCard: null, modalTab: 'info', openCard(c) { this.activeCard = c; this.modalTab = 'info'; } }">

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
                    {{-- Tabs: Info | Milestone --}}
                    <div style="display:flex; gap:24px; margin-bottom:16px; border-bottom:1px solid var(--km-border);">
                        <button type="button" @click="modalTab='info'"
                                :style="modalTab==='info' ? 'border-bottom:2px solid #f59e0b;color:#f59e0b;font-weight:600;' : 'color:var(--km-muted);'"
                                style="padding:10px 4px;font-size:13px;background:none;border:none;cursor:pointer;margin-bottom:-1px;">
                            Info & Progres
                        </button>
                        <button type="button" @click="modalTab='milestone'"
                                :style="modalTab==='milestone' ? 'border-bottom:2px solid #f59e0b;color:#f59e0b;font-weight:600;' : 'color:var(--km-muted);'"
                                style="padding:10px 4px;font-size:13px;background:none;border:none;cursor:pointer;margin-bottom:-1px;">
                            Milestone
                        </button>
                    </div>

                    {{-- TAB: Info & Progres --}}
                    <div x-show="modalTab==='info'">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                            <div style="flex:1;">
                                <div class="kanban-modal-label">Progres</div>
                                <div class="kanban-modal-progress">
                                    <div class="kanban-card-progress-bar"
                                         :style="`width: ${activeCard?.progres ?? 0}%; background: ${activeCard?.col_color}`"></div>
                                </div>
                            </div>
                            <div style="font-size:20px;font-weight:700;color:#f59e0b;" x-text="`${activeCard?.progres ?? 0}%`"></div>
                        </div>
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
                        <div class="kanban-modal-grid">
                            <div class="kanban-modal-field"><span class="kanban-modal-label">Bidang</span><span class="kanban-modal-value" x-text="activeCard?.bidang || '-'"></span></div>
                            <div class="kanban-modal-field"><span class="kanban-modal-label">Vendor</span><span class="kanban-modal-value" x-text="activeCard?.vendor || '-'"></span></div>
                            <div class="kanban-modal-field"><span class="kanban-modal-label">No SPK</span><span class="kanban-modal-value" x-text="activeCard?.no_spk || '-'" style="font-size:11px;"></span></div>
                            <div class="kanban-modal-field"><span class="kanban-modal-label">No SPMK</span><span class="kanban-modal-value" x-text="activeCard?.no_spmk || '-'" style="font-size:11px;"></span></div>
                            <div class="kanban-modal-field"><span class="kanban-modal-label">Nilai Pagu</span><span class="kanban-modal-value" x-text="activeCard?.nilai_pagu || '-'"></span></div>
                            <div class="kanban-modal-field"><span class="kanban-modal-label">Nilai Kontrak</span><span class="kanban-modal-value" x-text="activeCard?.nilai_kontrak || '-'"></span></div>
                            <div class="kanban-modal-field"><span class="kanban-modal-label">Mulai</span><span class="kanban-modal-value" x-text="activeCard?.tanggal_mulai || '-'"></span></div>
                            <div class="kanban-modal-field"><span class="kanban-modal-label">Akhir</span><span class="kanban-modal-value" x-text="activeCard?.tanggal_akhir || '-'"></span></div>
                            <div class="kanban-modal-field"><span class="kanban-modal-label">Hari Kerja</span><span class="kanban-modal-value" x-text="activeCard?.hari_kerja ? `${activeCard.hari_kerja} hari` : '-'"></span></div>
                            <div class="kanban-modal-field">
                                <span class="kanban-modal-label">Sisa Hari</span>
                                <span class="kanban-modal-value">
                                    <template x-if="activeCard?.sisa_hari === null || activeCard?.sisa_hari === undefined"><span>-</span></template>
                                    <template x-if="activeCard?.sisa_hari < 0"><span style="color:#dc2626;">⚠ Lewat <span x-text="Math.abs(activeCard?.sisa_hari)"></span> hari</span></template>
                                    <template x-if="activeCard?.sisa_hari >= 0"><span x-text="`${activeCard?.sisa_hari} hari`"></span></template>
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- TAB: Milestone Checklist --}}
                    <div x-show="modalTab==='milestone'">
                        <template x-if="!activeCard?.milestones || activeCard.milestones.length === 0">
                            <div style="text-align:center;padding:20px;color:var(--km-muted);font-size:13px;">Belum ada milestone</div>
                        </template>
                        <template x-for="ms in (activeCard?.milestones || [])" :key="ms.id">
                            <div style="background:var(--km-card-bg);border-radius:10px;margin-bottom:10px;overflow:hidden;border:1px solid var(--km-border);">
                                <div style="padding:10px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--km-border);">
                                    <div>
                                        <div style="font-weight:600;font-size:13px;color:var(--km-text);" x-text="`${ms.urutan}. ${ms.nama}`"></div>
                                        <div style="font-size:11px;color:var(--km-muted);">
                                            <span x-text="`${ms.target || '-'} · ${ms.progres}% · ${ms.status}`"></span>
                                        </div>
                                    </div>
                                    <template x-if="ms.items && ms.items.length > 0">
                                        <span style="font-size:11px;font-weight:600;color:#f59e0b;"
                                              x-text="`${ms.items.filter(i => i.is_done_vendor).length}/${ms.items.length}`"></span>
                                    </template>
                                </div>
                                <template x-if="ms.items && ms.items.length > 0">
                                    <div style="padding:4px 0 8px;">
                                        {{-- Column headers --}}
                                        <div style="display:grid;grid-template-columns:1fr 56px 56px;padding:4px 24px;border-bottom:1px solid var(--km-border);">
                                            <div style="font-size:10px;font-weight:700;color:var(--km-muted);text-transform:uppercase;">Item</div>
                                            <div style="text-align:center;font-size:10px;font-weight:700;color:var(--km-muted);text-transform:uppercase;">Vendor</div>
                                            <div style="text-align:center;font-size:10px;font-weight:700;color:var(--km-muted);text-transform:uppercase;">Admin</div>
                                        </div>
                                        <template x-for="section in ['kegiatan','deliverable']" :key="section">
                                            <template x-if="ms.items.filter(i => i.tipe === section).length > 0">
                                                <div>
                                                    <div style="padding:8px 24px 2px;font-size:10px;font-weight:700;color:var(--km-muted);text-transform:uppercase;letter-spacing:0.05em;"
                                                         x-text="section === 'kegiatan' ? 'Kegiatan' : 'Deliverable'"></div>
                                                    <template x-for="ci in ms.items.filter(i => i.tipe === section)" :key="ci.id">
                                                        <div style="display:grid;grid-template-columns:1fr 56px 56px;align-items:center;padding:5px 24px;">
                                                            <div style="font-size:12px;color:var(--km-text);"
                                                                 :style="(ci.is_done_vendor && ci.is_done_admin) ? 'text-decoration:line-through;opacity:0.4;' : ''"
                                                                 x-text="ci.nama"></div>
                                                            <div style="text-align:center;">
                                                                <input type="checkbox" :checked="ci.is_done_vendor"
                                                                       @if(auth()->user()->hasRole('vendor'))
                                                                           wire:click="toggleVendor(ci.id)" @click="ci.is_done_vendor = !ci.is_done_vendor"
                                                                           style="width:16px;height:16px;accent-color:#f59e0b;cursor:pointer;"
                                                                       @else
                                                                           disabled
                                                                           style="width:16px;height:16px;accent-color:#f59e0b;cursor:default;opacity:0.6;"
                                                                       @endif
                                                                >
                                                            </div>
                                                            <div style="text-align:center;">
                                                                <input type="checkbox" :checked="ci.is_done_admin"
                                                                       @if(auth()->user()->hasAnyRole(['pptk','ppk','admin_bidang','super_admin']))
                                                                           :disabled="!ci.is_done_vendor"
                                                                           x-bind:style="ci.is_done_vendor ? 'width:16px;height:16px;accent-color:#f59e0b;cursor:pointer;' : 'width:16px;height:16px;accent-color:#f59e0b;cursor:default;opacity:0.3;'"
                                                                           wire:click="toggleAdmin(ci.id)" @click="if(ci.is_done_vendor) ci.is_done_admin = !ci.is_done_admin"
                                                                       @else
                                                                           disabled
                                                                           style="width:16px;height:16px;accent-color:#f59e0b;cursor:default;opacity:0.6;"
                                                                       @endif
                                                                >
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
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
