<div>
    <style>
        .mc-root { display: flex; flex-direction: column; gap: 16px; }
        .mc-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
        }
        .dark .mc-card { background: #1f2937; border-color: #374151; }
        .mc-header {
            padding: 12px 16px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .dark .mc-header { background: #111827; border-color: #374151; }
        .mc-title {
            font-weight: 600;
            font-size: 14px;
            color: #1f2937;
        }
        .dark .mc-title { color: #f3f4f6; }
        .mc-meta {
            display: flex;
            gap: 12px;
            font-size: 12px;
            color: #6b7280;
        }
        .dark .mc-meta { color: #9ca3af; }
        .mc-badge {
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
        }
        .mc-badge-kegiatan { background: #dbeafe; color: #1d4ed8; }
        .mc-badge-deliverable { background: #fef3c7; color: #92400e; }
        .dark .mc-badge-kegiatan { background: #1e3a5f; color: #93c5fd; }
        .dark .mc-badge-deliverable { background: #451a03; color: #fcd34d; }
        .mc-progress {
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            background: #ecfdf5;
            color: #065f46;
        }
        .dark .mc-progress { background: #064e3b; color: #6ee7b7; }
        .mc-items { padding: 8px 0; }
        .mc-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            transition: background 0.1s;
        }
        .mc-item:hover { background: #f9fafb; }
        .dark .mc-item:hover { background: #111827; }
        .mc-checkbox {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            cursor: pointer;
            accent-color: #f59e0b;
            flex-shrink: 0;
        }
        .mc-checkbox:disabled {
            cursor: default;
            opacity: 0.4;
        }
        .mc-item-name {
            flex: 1;
            font-size: 13px;
            color: #374151;
        }
        .dark .mc-item-name { color: #d1d5db; }
        .mc-item-done {
            text-decoration: line-through;
            opacity: 0.6;
        }
        .mc-col-label {
            font-size: 10px;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            text-align: center;
            width: 50px;
            flex-shrink: 0;
        }
        .mc-empty {
            padding: 16px;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
        }
    </style>

    <div class="mc-root">
        @forelse ($milestones as $milestone)
            @php
                $items = $milestone->checklistItems;
                $vendorDone = $items->where('is_done_vendor', true)->count();
                $adminDone = $items->where('is_done_admin', true)->count();
                $total = $items->count();
            @endphp
            <div class="mc-card">
                <div class="mc-header">
                    <div>
                        <div class="mc-title">{{ $milestone->urutan }}. {{ $milestone->nama }}</div>
                        <div class="mc-meta">
                            <span>Target: {{ $milestone->tanggal_target?->format('d M Y') ?? '-' }}</span>
                            <span>Progres: {{ $milestone->progres_target_persen }}%</span>
                            <span>Status: {{ $milestone->status_label }}</span>
                        </div>
                    </div>
                    @if ($total > 0)
                        <div class="mc-progress">{{ $vendorDone }}/{{ $total }} vendor · {{ $adminDone }}/{{ $total }} admin</div>
                    @endif
                </div>

                @if ($total > 0)
                    <div class="mc-items">
                        {{-- Column headers --}}
                        <div style="display:flex; align-items:center; gap:10px; padding:4px 16px; border-bottom:1px solid #f3f4f6;">
                            <div style="width:18px;"></div>
                            <div style="flex:1;"></div>
                            <div class="mc-col-label">Vendor</div>
                            <div class="mc-col-label">Admin</div>
                        </div>

                        @foreach ($items as $item)
                            <div class="mc-item">
                                <span class="mc-badge {{ $item->tipe === 'kegiatan' ? 'mc-badge-kegiatan' : 'mc-badge-deliverable' }}">
                                    {{ $item->tipe === 'kegiatan' ? 'K' : 'D' }}
                                </span>
                                <span class="mc-item-name {{ ($item->is_done_vendor && $item->is_done_admin) ? 'mc-item-done' : '' }}">
                                    {{ $item->nama }}
                                </span>
                                <input type="checkbox"
                                       class="mc-checkbox"
                                       {{ $item->is_done_vendor ? 'checked' : '' }}
                                       {{ $isVendor ? '' : 'disabled' }}
                                       wire:click="toggleVendor({{ $item->id }})"
                                       title="Vendor: {{ $item->is_done_vendor ? 'Selesai' : 'Belum' }}">
                                <input type="checkbox"
                                       class="mc-checkbox"
                                       {{ $item->is_done_admin ? 'checked' : '' }}
                                       {{ $isAdmin ? '' : 'disabled' }}
                                       wire:click="toggleAdmin({{ $item->id }})"
                                       title="Admin: {{ $item->is_done_admin ? 'Dikonfirmasi' : 'Belum' }}">
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mc-empty">Tidak ada checklist item</div>
                @endif
            </div>
        @empty
            <div class="mc-empty">Belum ada milestone</div>
        @endforelse
    </div>
</div>
