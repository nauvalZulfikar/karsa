<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
    ];

    protected $casts = [
        'tanggal_target'         => 'date',
        'tanggal_selesai_aktual' => 'date',
        'progres_target_persen'  => 'decimal:2',
    ];

    public static array $statusOptions = [
        'belum_mulai'     => 'Belum Mulai',
        'sedang_berjalan' => 'Sedang Berjalan',
        'selesai'         => 'Selesai',
        'terlambat'       => 'Terlambat',
    ];

    public static array $statusColors = [
        'belum_mulai'     => 'gray',
        'sedang_berjalan' => 'info',
        'selesai'         => 'success',
        'terlambat'       => 'danger',
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

    public function getStatusLabelAttribute(): string
    {
        return static::$statusOptions[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return static::$statusColors[$this->status] ?? 'gray';
    }
}
