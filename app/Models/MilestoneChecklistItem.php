<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilestoneChecklistItem extends Model
{
    protected $table = 'milestone_checklist_items';

    protected $fillable = [
        'milestone_pekerjaan_id',
        'tipe',
        'nama',
        'is_done_vendor',
        'vendor_done_at',
        'is_done_admin',
        'admin_done_at',
        'admin_done_by',
    ];

    protected $casts = [
        'is_done_vendor' => 'boolean',
        'is_done_admin'  => 'boolean',
        'vendor_done_at' => 'datetime',
        'admin_done_at'  => 'datetime',
    ];

    public function milestonePekerjaan(): BelongsTo
    {
        return $this->belongsTo(MilestonePekerjaan::class, 'milestone_pekerjaan_id');
    }

    public function adminDoneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_done_by');
    }
}
