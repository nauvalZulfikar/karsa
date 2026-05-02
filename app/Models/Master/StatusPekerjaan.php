<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class StatusPekerjaan extends Model
{
    use LogsActivity;

    protected $table = 'status_pekerjaan';

    protected $fillable = [
        'nama',
        'kode',
        'warna',
        'urutan',
        'keterangan',
        'is_final',
    ];

    protected $casts = [
        'is_final' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('urutan', 'asc');
    }
}
