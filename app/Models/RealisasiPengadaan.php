<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RealisasiPengadaan extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'realisasi_pengadaan';

    protected $fillable = [
        'rencana_pengadaan_id', 'pekerjaan_id', 'perusahaan_id',
        'tanggal_realisasi', 'volume_beli', 'harga_aktual',
        'volume_dipakai', 'volume_sisa',
        'foto_invoice_path', 'foto_material_path',
        'catatan_vendor', 'status', 'catatan_pptk',
        'verified_by', 'verified_at', 'created_by',
    ];

    protected $casts = [
        'tanggal_realisasi' => 'date',
        'volume_beli'       => 'decimal:3',
        'harga_aktual'      => 'decimal:2',
        'volume_dipakai'    => 'decimal:3',
        'volume_sisa'       => 'decimal:3',
        'verified_at'       => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function rencanaPengadaan()
    {
        return $this->belongsTo(RencanaPengadaan::class);
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function perusahaan()
    {
        return $this->belongsTo(\App\Models\Master\Perusahaan::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'verified_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function getTotalAktualAttribute(): float
    {
        return (float) $this->volume_beli * (float) $this->harga_aktual;
    }

    public function getMarkupPersenAttribute(): ?float
    {
        $rencana = $this->rencanaPengadaan;
        if (!$rencana || $rencana->harga_satuan_rencana <= 0) return null;
        return (((float)$this->harga_aktual - (float)$rencana->harga_satuan_rencana) / (float)$rencana->harga_satuan_rencana) * 100;
    }

    public function getIsMarkupAlertAttribute(): bool
    {
        $persen = $this->markup_persen;
        return $persen !== null && $persen > 15;
    }
}
