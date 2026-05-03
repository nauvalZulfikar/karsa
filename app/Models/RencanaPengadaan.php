<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RencanaPengadaan extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'rencana_pengadaan';

    protected $fillable = [
        'pekerjaan_id', 'nama_item', 'satuan',
        'volume_rencana', 'harga_satuan_rencana', 'keterangan', 'created_by',
    ];

    protected $casts = [
        'volume_rencana'       => 'decimal:3',
        'harga_satuan_rencana' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function realisasi()
    {
        return $this->hasMany(RealisasiPengadaan::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function getTotalRencanaAttribute(): float
    {
        return (float) $this->volume_rencana * (float) $this->harga_satuan_rencana;
    }

    public function getTotalVolumeDipakaiAttribute(): float
    {
        return (float) $this->realisasi()->where('status', 'verified')->sum('volume_dipakai');
    }

    public function getSelisihVolumeAttribute(): float
    {
        return (float) $this->volume_rencana - $this->total_volume_dipakai;
    }

    public function getTotalRealisasiNilaiAttribute(): float
    {
        return (float) $this->realisasi()->where('status', 'verified')
            ->selectRaw('SUM(volume_beli * harga_aktual) as total')
            ->value('total') ?? 0;
    }

    public function getIsAlertAttribute(): bool
    {
        // alert when verified usage exceeds planned volume by more than 5%
        if ($this->volume_rencana <= 0) return false;
        return $this->total_volume_dipakai > ($this->volume_rencana * 1.05);
    }
}
