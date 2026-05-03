<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LaporanHarian extends Model
{
    use LogsActivity;

    protected $table = 'laporan_harian';

    protected $fillable = [
        'pekerjaan_id', 'perusahaan_id', 'user_id', 'jenis',
        'foto_original_path', 'foto_stamped_path',
        'latitude', 'longitude', 'catatan', 'status',
        'alasan_rejected', 'submitted_at', 'tanggal_laporan',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'tanggal_laporan' => 'date',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function pekerjaan()
    {
        return $this->belongsTo(Pekerjaan::class);
    }

    public function perusahaan()
    {
        return $this->belongsTo(\App\Models\Master\Perusahaan::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeHariIni($q)
    {
        return $q->whereDate('tanggal_laporan', today());
    }

    public function scopeMasuk($q)
    {
        return $q->where('jenis', 'masuk');
    }

    public function scopePulang($q)
    {
        return $q->where('jenis', 'pulang');
    }
}
