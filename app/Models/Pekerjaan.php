<?php

namespace App\Models;

use App\Models\Master\Bidang;
use App\Models\Master\JenisPekerjaan;
use App\Models\Master\Perusahaan;
use App\Models\Master\StatusPekerjaan;
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

    public function scopeTahun($query, $tahun)
    {
        return $query->where('tahun_anggaran', $tahun);
    }

    public function scopeBidang($query, $bidangId)
    {
        return $query->where('bidang_id', $bidangId);
    }
}
