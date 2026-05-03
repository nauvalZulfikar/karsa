<?php

namespace App\Models;

use App\Models\LaporanHarian;
use App\Models\Master\Bidang;
use App\Models\RencanaPengadaan;
use App\Models\RealisasiPengadaan;
use App\Models\Master\JenisPekerjaan;
use App\Models\Master\Perusahaan;
use App\Models\Master\StatusPekerjaan;
use App\Models\PekerjaanPersonil;
use App\Services\DeadlineCalculatorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pekerjaan extends Model
{
    protected $table = 'pekerjaan';
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'bidang_id', 'jenis_pekerjaan_id', 'perusahaan_id', 'status_pekerjaan_id',
        'tahun_anggaran', 'nama_pekerjaan', 'nilai_pagu', 'nilai_kontrak',
        'no_spk', 'tanggal_spk', 'no_spmk', 'tanggal_spmk',
        'tanggal_mulai', 'tanggal_akhir', 'hari_kerja', 'satuan_waktu',
        'progres_persen', 'catatan', 'kickoff_dokumen_path',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'tanggal_spk' => 'date',
        'tanggal_spmk' => 'date',
        'tanggal_mulai' => 'date',
        'tanggal_akhir' => 'date',
        'nilai_pagu' => 'decimal:2',
        'nilai_kontrak' => 'decimal:2',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function bidang()
    {
        return $this->belongsTo(Bidang::class);
    }

    public function jenisPekerjaan()
    {
        return $this->belongsTo(JenisPekerjaan::class);
    }

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class);
    }

    public function statusPekerjaan()
    {
        return $this->belongsTo(StatusPekerjaan::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function personil()
    {
        return $this->hasMany(PekerjaanPersonil::class);
    }

    public function vendors()
    {
        return $this->belongsToMany(
            \App\Models\Master\Perusahaan::class,
            'pekerjaan_vendor'
        )->withTimestamps();
    }

    public function laporanHarian()
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function tenagaAhli()
    {
        return $this->belongsToMany(
            \App\Models\Master\TenagaAhli::class,
            'pekerjaan_personil'
        )->withPivot(['jabatan_kontrak', 'nilai_honor_kontrak', 'tanggal_mulai_tugas', 'tanggal_akhir_tugas', 'is_active'])
         ->withTimestamps();
    }

    public function rencanaPengadaan()
    {
        return $this->hasMany(RencanaPengadaan::class);
    }

    public function realisasiPengadaan()
    {
        return $this->hasMany(RealisasiPengadaan::class);
    }

    public function dokumen()
    {
        return $this->hasMany(Dokumen::class);
    }

    public function terminPembayaran()
    {
        return $this->hasMany(TerminPembayaran::class);
    }

    public function milestones()
    {
        return $this->hasMany(MilestonePekerjaan::class)->orderBy('urutan');
    }

    public function scopeTahun($query, $tahun)
    {
        return $query->where('tahun_anggaran', $tahun);
    }

    public function scopeBidang($query, $bidangId)
    {
        return $query->where('bidang_id', $bidangId);
    }

    public function getSisaHariAttribute(): ?int
    {
        if (!$this->tanggal_akhir) return null;
        return app(DeadlineCalculatorService::class)
            ->hitungSisaHariKerja($this->tanggal_akhir, $this->satuan_waktu);
    }

    public function getPersenWaktuTerpakaiAttribute(): ?float
    {
        return app(DeadlineCalculatorService::class)
            ->hitungPersenWaktu($this->tanggal_mulai, $this->tanggal_akhir, $this->satuan_waktu);
    }

    public function getStatusWaktuAttribute(): string
    {
        $sudahSelesai = $this->statusPekerjaan?->kode === 'selesai';
        return app(DeadlineCalculatorService::class)
            ->getStatusWaktu($this->sisa_hari, $sudahSelesai);
    }

    public function getStatusWaktuLabelAttribute(): string
    {
        return match($this->status_waktu) {
            'aman'       => 'Aman',
            'waspada'    => 'Waspada',
            'kritis'     => 'Kritis',
            'terlambat'  => 'Terlambat',
            'selesai'    => 'Selesai',
            default      => 'Belum Mulai',
        };
    }

    public function getStatusWaktuColorAttribute(): string
    {
        return match($this->status_waktu) {
            'aman'      => 'success',
            'waspada'   => 'warning',
            'kritis'    => 'danger',
            'terlambat' => 'danger',
            'selesai'   => 'success',
            default     => 'gray',
        };
    }
}
