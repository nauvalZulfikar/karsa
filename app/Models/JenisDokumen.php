<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class JenisDokumen extends Model
{
    protected $table = 'jenis_dokumen';

    protected $fillable = ['key', 'label', 'color', 'urutan', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan'    => 'integer',
    ];

    protected static function booted(): void
    {
        // Auto-slug key dari label kalau kosong.
        static::saving(function (self $jd) {
            if (blank($jd->key)) {
                $jd->key = Str::slug($jd->label, '_');
            }
        });

        // Buang cache tiap ada perubahan supaya dropdown ikut ter-update.
        $forget = fn () => Cache::forget('jenis_dokumen_map');
        static::saved($forget);
        static::deleted($forget);
    }
}
