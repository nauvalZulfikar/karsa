<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Default (seed) tipe dokumen. Sumber kebenaran runtime = tabel jenis_dokumen
     * (bisa ditambah/hapus lewat UI). Array ini dipakai untuk seed migrasi &
     * fallback kalau tabel belum ada / kosong. Lihat tipeOptions()/tipeColors().
     */
    public static array $tipeOptions = [
        'kak'                 => 'KAK',
        'penawaran'           => 'Dokumen Penawaran',
        'rab_negosiasi'       => 'RAB Negosiasi',
        'kontrak'             => 'Kontrak',
        'addendum'            => 'Addendum',
        'spmk'                => 'SPMK',
        'laporan_pendahuluan' => 'Laporan Pendahuluan',
        'laporan_antara'      => 'Laporan Antara',
        'laporan_mingguan'    => 'Laporan Mingguan',
        'laporan_akhir'       => 'Laporan Akhir',
        'laporan_invoice'     => 'Laporan Invoice',
        'lembar_asistensi'    => 'Lembar Asistensi',
        'notulensi_rapat'     => 'Notulensi Rapat',
        'bast'                => 'BAST',
        'ba_penyerahan'       => 'Berita Acara Penyerahan Pekerjaan',
        'foto_progress'       => 'Foto Progress',
        'gambar_kerja'        => 'Gambar Kerja',
        'lainnya'             => 'Lainnya',
    ];

    public static array $tipeColors = [
        'kak'                 => 'info',
        'penawaran'           => 'info',
        'rab_negosiasi'       => 'warning',
        'kontrak'             => 'primary',
        'addendum'            => 'warning',
        'spmk'                => 'primary',
        'laporan_pendahuluan' => 'gray',
        'laporan_antara'      => 'gray',
        'laporan_mingguan'    => 'gray',
        'laporan_akhir'       => 'success',
        'laporan_invoice'     => 'warning',
        'lembar_asistensi'    => 'gray',
        'notulensi_rapat'     => 'info',
        'bast'                => 'success',
        'ba_penyerahan'       => 'success',
        'foto_progress'       => 'gray',
        'gambar_kerja'        => 'gray',
        'lainnya'             => 'gray',
    ];

    /** Peta tipe dokumen aktif (DB kalau ada, else fallback default). Dicache. */
    public static function tipeMap(): array
    {
        if ($cached = Cache::get('jenis_dokumen_map')) {
            return $cached;
        }

        try {
            $rows = JenisDokumen::where('is_active', true)
                ->orderBy('urutan')->orderBy('label')
                ->get(['key', 'label', 'color']);

            if ($rows->isNotEmpty()) {
                $map = [
                    'options' => $rows->pluck('label', 'key')->all(),
                    'colors'  => $rows->pluck('color', 'key')->all(),
                ];
                Cache::forever('jenis_dokumen_map', $map);

                return $map;
            }
        } catch (\Throwable $e) {
            // tabel belum dimigrasi — pakai default
        }

        return ['options' => self::$tipeOptions, 'colors' => self::$tipeColors];
    }

    public static function tipeOptions(): array
    {
        return self::tipeMap()['options'];
    }

    public static function tipeColors(): array
    {
        return self::tipeMap()['colors'];
    }

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
        return static::tipeOptions()[$this->tipe] ?? $this->tipe;
    }

    public function getTipeColorAttribute(): string
    {
        return static::tipeColors()[$this->tipe] ?? 'gray';
    }

    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) return '-';
        $kb = $this->file_size / 1024;
        if ($kb < 1024) return number_format($kb, 1) . ' KB';
        return number_format($kb / 1024, 2) . ' MB';
    }
}
