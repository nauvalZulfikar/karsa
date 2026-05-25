<?php

namespace App\Livewire;

use App\Models\MilestoneChecklistItem;
use App\Models\MilestonePekerjaan;
use Livewire\Component;

class MilestoneChecklist extends Component
{
    public int $pekerjaanId;

    public function mount(int $pekerjaanId): void
    {
        $this->pekerjaanId = $pekerjaanId;
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

    public function render()
    {
        $milestones = MilestonePekerjaan::where('pekerjaan_id', $this->pekerjaanId)
            ->with('checklistItems')
            ->orderBy('urutan')
            ->get();

        $isVendor = auth()->user()->hasRole('vendor');
        $isAdmin = auth()->user()->hasAnyRole(['pptk', 'ppk', 'admin_bidang', 'super_admin']);

        return view('livewire.milestone-checklist', compact('milestones', 'isVendor', 'isAdmin'));
    }
}
