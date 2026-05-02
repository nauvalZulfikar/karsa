<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TenagaAhli extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'tenaga_ahli';

    protected $fillable = [
        'perusahaan_id',
        'nama',
        'nik',
        'npwp',
        'jabatan_keahlian',
        'sertifikasi',
        'alamat',
        'email',
        'no_telp',
        'foto_path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    public function pekerjaan()
    {
        return $this->belongsToMany(
            \App\Models\Pekerjaan::class,
            'pekerjaan_personil'
        )->withPivot(['jabatan_kontrak', 'nilai_honor_kontrak', 'tanggal_mulai_tugas', 'tanggal_akhir_tugas', 'is_active'])
         ->withTimestamps();
    }

    public function pekerjaanAktif()
    {
        return $this->pekerjaan()
            ->wherePivot('is_active', true)
            ->whereHas('statusPekerjaan', fn ($q) => $q->where('kode', '!=', 'selesai'));
    }
}
