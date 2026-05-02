<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class HariLibur extends Model
{
    protected $table = 'hari_libur';

    protected $fillable = ['tanggal', 'nama', 'is_cuti_bersama'];

    protected $casts = [
        'tanggal' => 'date',
        'is_cuti_bersama' => 'boolean',
    ];

    public static function getLiburDates(int $year): array
    {
        return static::whereYear('tanggal', $year)
            ->pluck('tanggal')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();
    }
}
