<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Dokumen extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'dokumen';

    protected $fillable = [
        'pekerjaan_id', 'tipe', 'nama_dokumen', 'versi',
        'file_path', 'file_original_name', 'file_size',
        'keterangan', 'created_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public static array $tipeOptions = [
        'kak'              => 'KAK',
        'kontrak'          => 'Kontrak',
        'addendum'         => 'Addendum',
        'spmk'             => 'SPMK',
        'bast'             => 'BAST',
        'laporan_mingguan' => 'Laporan Mingguan',
        'laporan_akhir'    => 'Laporan Akhir',
        'foto_progress'    => 'Foto Progress',
        'gambar_kerja'     => 'Gambar Kerja',
        'lainnya'          => 'Lainnya',
    ];

    public static array $tipeColors = [
        'kak'              => 'info',
        'kontrak'          => 'primary',
        'addendum'         => 'warning',
        'spmk'             => 'primary',
        'bast'             => 'success',
        'laporan_mingguan' => 'gray',
        'laporan_akhir'    => 'success',
        'foto_progress'    => 'gray',
        'gambar_kerja'     => 'gray',
        'lainnya'          => 'gray',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function getTipeLabelAttribute(): string
    {
        return static::$tipeOptions[$this->tipe] ?? $this->tipe;
    }

    public function getTipeColorAttribute(): string
    {
        return static::$tipeColors[$this->tipe] ?? 'gray';
    }

    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) return '-';
        $kb = $this->file_size / 1024;
        if ($kb < 1024) return number_format($kb, 1) . ' KB';
        return number_format($kb / 1024, 2) . ' MB';
    }
}
