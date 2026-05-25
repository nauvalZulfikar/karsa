<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MilestonePekerjaan extends Model
{
    use LogsActivity;

    protected $table = 'milestone_pekerjaan';

    protected $fillable = [
        'pekerjaan_id', 'urutan', 'nama', 'deskripsi',
        'tanggal_target', 'tanggal_selesai_aktual',
        'progres_target_persen', 'status', 'sumber', 'catatan',
        'alasan_penolakan', 'confirmed_by', 'confirmed_at',
    ];

    protected $casts = [
        'tanggal_target'         => 'date',
        'tanggal_selesai_aktual' => 'date',
        'progres_target_persen'  => 'decimal:2',
        'confirmed_at'           => 'datetime',
    ];

    public static array $statusOptions = [
        'belum_mulai'     => 'Belum Mulai',
        'sedang_berjalan' => 'Sedang Berjalan',
        'selesai'         => 'Selesai',
        'terlambat'       => 'Terlambat',
        'diajukan_vendor' => 'Diajukan Vendor',
        'dikonfirmasi'    => 'Dikonfirmasi',
        'ditolak'         => 'Ditolak',
    ];

    public static array $statusColors = [
        'belum_mulai'     => 'gray',
        'sedang_berjalan' => 'info',
        'selesai'         => 'success',
        'terlambat'       => 'danger',
        'diajukan_vendor' => 'warning',
        'dikonfirmasi'    => 'success',
        'ditolak'         => 'danger',
    ];

    public static array $sumberLabels = [
        'kontrak'      => 'Dari Kontrak',
        'generated_ai' => 'Generated AI',
        'manual'       => 'Manual',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(MilestoneChecklistItem::class, 'milestone_pekerjaan_id');
    }

    public function getVendorProgressAttribute(): string
    {
        $items = $this->checklistItems;
        if ($items->isEmpty()) return '-';
        $done = $items->where('is_done_vendor', true)->count();
        return $done . '/' . $items->count();
    }

    public function getAllVendorDoneAttribute(): bool
    {
        $items = $this->checklistItems;
        if ($items->isEmpty()) return true;
        return $items->every(fn ($i) => $i->is_done_vendor);
    }

    public function getStatusLabelAttribute(): string
    {
        return static::$statusOptions[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return static::$statusColors[$this->status] ?? 'gray';
    }
}
