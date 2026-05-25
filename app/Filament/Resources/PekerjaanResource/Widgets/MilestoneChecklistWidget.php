<?php

namespace App\Filament\Resources\PekerjaanResource\Widgets;

use App\Models\MilestoneChecklistItem;
use App\Models\MilestonePekerjaan;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class MilestoneChecklistWidget extends Widget
{
    protected static string $view = 'filament.widgets.milestone-checklist-widget';
    protected int|string|array $columnSpan = 'full';
    protected static bool $isLazy = false;

    public ?Model $record = null;

    public function getMilestones()
    {
        if (!$this->record) return collect();

        return MilestonePekerjaan::where('pekerjaan_id', $this->record->id)
            ->with('checklistItems')
            ->orderBy('urutan')
            ->get();
    }

    public function toggleVendor(int $itemId): void
    {
        $item = MilestoneChecklistItem::find($itemId);
        if (!$item) return;

        abort_unless(
            auth()->user()->hasRole('vendor')
            && $item->milestonePekerjaan->pekerjaan->perusahaan_id === auth()->user()->perusahaan_id,
            403
        );

        $item->update([
            'is_done_vendor' => !$item->is_done_vendor,
            'vendor_done_at' => !$item->is_done_vendor ? now() : null,
        ]);
    }

    public function toggleAdmin(int $itemId): void
    {
        $item = MilestoneChecklistItem::find($itemId);
        if (!$item) return;

        abort_unless(
            auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin']),
            403
        );

        $item->update([
            'is_done_admin' => !$item->is_done_admin,
            'admin_done_at' => !$item->is_done_admin ? now() : null,
            'admin_done_by' => !$item->is_done_admin ? auth()->id() : null,
        ]);
    }
}
