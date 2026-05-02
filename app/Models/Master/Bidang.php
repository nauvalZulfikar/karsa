<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Bidang extends Model
{
    use LogsActivity;

    protected $table = 'bidang';

    protected $fillable = [
        'nama',
        'kode',
        'kepala_bidang',
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

    public function pekerjaan()
    {
        return null;
    }
}
