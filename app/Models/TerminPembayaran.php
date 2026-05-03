<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TerminPembayaran extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'termin_pembayaran';

    protected $fillable = [
        'pekerjaan_id', 'nomor_termin', 'nama_termin',
        'nilai_termin', 'persen_progres_syarat',
        'tanggal_pengajuan', 'tanggal_persetujuan', 'tanggal_bayar',
        'status', 'catatan_pptk', 'catatan_ppk',
        'dokumen_path', 'approved_by', 'created_by',
    ];

    protected $casts = [
        'nilai_termin'          => 'decimal:2',
        'persen_progres_syarat' => 'decimal:2',
        'tanggal_pengajuan'     => 'date',
        'tanggal_persetujuan'   => 'date',
        'tanggal_bayar'         => 'date',
    ];

    public static array $statusOptions = [
        'draft'     => 'Draft',
        'diajukan'  => 'Diajukan',
        'disetujui' => 'Disetujui',
        'dibayar'   => 'Dibayar',
        'ditolak'   => 'Ditolak',
    ];

    public static array $statusColors = [
        'draft'     => 'gray',
        'diajukan'  => 'warning',
        'disetujui' => 'info',
        'dibayar'   => 'success',
        'ditolak'   => 'danger',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return static::$statusOptions[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return static::$statusColors[$this->status] ?? 'gray';
    }

    public function getIsSyaratTerpenuhiAttribute(): bool
    {
        $progres = $this->pekerjaan?->progres_persen ?? 0;
        return (float)$progres >= (float)$this->persen_progres_syarat;
    }
}
