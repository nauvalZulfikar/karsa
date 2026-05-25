<x-filament-widgets::widget>
    <style>
        .mc-section-title {
            font-size: 16px; font-weight: 700; color: #1f2937;
            padding: 16px 0 8px; display: flex; align-items: center; gap: 8px;
        }
        .dark .mc-section-title { color: #f3f4f6; }
        .mc-card {
            background: #fff; border: 1px solid #e5e7eb;
            border-radius: 12px; overflow: hidden; margin-bottom: 12px;
        }
        .dark .mc-card { background: #1f2937; border-color: #374151; }
        .mc-header {
            padding: 10px 16px; background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }
        .dark .mc-header { background: #111827; border-color: #374151; }
        .mc-title { font-weight: 600; font-size: 13px; color: #1f2937; }
        .dark .mc-title { color: #f3f4f6; }
        .mc-meta { font-size: 11px; color: #6b7280; display: flex; gap: 10px; margin-top: 2px; }
        .dark .mc-meta { color: #9ca3af; }
        .mc-progress {
            padding: 2px 10px; border-radius: 9999px;
            font-size: 11px; font-weight: 600;
            background: #ecfdf5; color: #065f46; white-space: nowrap;
        }
        .dark .mc-progress { background: #064e3b; color: #6ee7b7; }
        .mc-col-header {
            display: flex; align-items: center; gap: 10px;
            padding: 4px 16px; border-bottom: 1px solid #f3f4f6;
            font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase;
        }
        .dark .mc-col-header { border-color: #374151; }
        .mc-item {
            display: flex; align-items: center; gap: 10px;
            padding: 6px 16px; transition: background 0.1s;
        }
        .mc-item:hover { background: #f9fafb; }
        .dark .mc-item:hover { background: #111827; }
        .mc-badge {
            padding: 1px 6px; border-radius: 9999px;
            font-size: 10px; font-weight: 600; flex-shrink: 0;
        }
        .mc-badge-k { background: #dbeafe; color: #1d4ed8; }
        .mc-badge-d { background: #fef3c7; color: #92400e; }
        .dark .mc-badge-k { background: #1e3a5f; color: #93c5fd; }
        .dark .mc-badge-d { background: #451a03; color: #fcd34d; }
        .mc-name {
            flex: 1; font-size: 13px; color: #374151;
        }
        .dark .mc-name { color: #d1d5db; }
        .mc-done { text-decoration: line-through; opacity: 0.5; }
        .mc-check {
            width: 16px; height: 16px; border-radius: 3px;
            cursor: pointer; accent-color: #f59e0b; flex-shrink: 0;
        }
        .mc-check:disabled { cursor: default; opacity: 0.3; }
        .mc-check-col { width: 50px; text-align: center; flex-shrink: 0; }
        .mc-empty { padding: 12px 16px; text-align: center; font-size: 12px; color: #9ca3af; }
    </style>

    @php
        $milestones = $this->getMilestones();
        $isVendor = auth()->user()->hasRole('vendor');
        $isAdmin = auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin']);
    @endphp

    <div class="mc-section-title">📋 Milestone Checklist</div>

    @forelse ($milestones as $milestone)
        @php
            $items = $milestone->checklistItems;
            $vDone = $items->where('is_done_vendor', true)->count();
            $aDone = $items->where('is_done_admin', true)->count();
            $total = $items->count();
        @endphp
        <div class="mc-card" wire:key="mc-{{ $milestone->id }}">
            <div class="mc-header">
                <div>
                    <div class="mc-title">{{ $milestone->urutan }}. {{ $milestone->nama }}</div>
                    <div class="mc-meta">
                        <span>📅 {{ $milestone->tanggal_target?->format('d M Y') ?? '-' }}</span>
                        <span>🎯 {{ $milestone->progres_target_persen }}%</span>
                        <span>{{ $milestone->status_label }}</span>
                    </div>
                </div>
                @if ($total > 0)
                    <div class="mc-progress">✓ {{ $vDone }}/{{ $total }} vendor · {{ $aDone }}/{{ $total }} admin</div>
                @endif
            </div>

            @if ($total > 0)
                <div class="mc-col-header">
                    <div style="width:30px;"></div>
                    <div style="flex:1;">Item</div>
                    <div class="mc-check-col">Vendor</div>
                    <div class="mc-check-col">Admin</div>
                </div>
                @foreach ($items as $item)
                    <div class="mc-item" wire:key="mci-{{ $item->id }}">
                        <span class="mc-badge {{ $item->tipe === 'kegiatan' ? 'mc-badge-k' : 'mc-badge-d' }}">
                            {{ $item->tipe === 'kegiatan' ? 'Kegiatan' : 'Deliverable' }}
                        </span>
                        <span class="mc-name {{ ($item->is_done_vendor && $item->is_done_admin) ? 'mc-done' : '' }}">
                            {{ $item->nama }}
                        </span>
                        <div class="mc-check-col">
                            <input type="checkbox"
                                   class="mc-check"
                                   {{ $item->is_done_vendor ? 'checked' : '' }}
                                   {{ $isVendor ? '' : 'disabled' }}
                                   wire:click="toggleVendor({{ $item->id }})">
                        </div>
                        <div class="mc-check-col">
                            <input type="checkbox"
                                   class="mc-check"
                                   {{ $item->is_done_admin ? 'checked' : '' }}
                                   {{ $isAdmin ? '' : 'disabled' }}
                                   wire:click="toggleAdmin({{ $item->id }})">
                        </div>
                    </div>
                @endforeach
            @else
                <div class="mc-empty">Tidak ada checklist item</div>
            @endif
        </div>
    @empty
        <div class="mc-empty">Belum ada milestone</div>
    @endforelse
</x-filament-widgets::widget>
