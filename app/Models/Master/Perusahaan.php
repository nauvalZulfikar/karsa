<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Perusahaan extends Model
{
    use LogsActivity, SoftDeletes;

    protected $table = 'perusahaan';

    protected $fillable = [
        'nama',
        'singkatan',
        'jenis',
        'npwp',
        'alamat',
        'no_telp',
        'email',
        'pic_nama',
        'pic_telp',
        'is_blacklisted',
        'catatan',
    ];

    protected $casts = [
        'is_blacklisted' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }

    public function scopeAktif($query)
    {
        return $query->where('is_blacklisted', false);
    }

    public function tenagaAhli()
    {
        return $this->hasMany(TenagaAhli::class, 'perusahaan_id');
    }
}
