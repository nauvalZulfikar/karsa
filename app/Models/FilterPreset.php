<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FilterPreset extends Model
{
    protected $table = 'filter_presets';

    protected $fillable = ['user_id', 'resource', 'nama', 'filters'];

    protected $casts = ['filters' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(string $resource): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('user_id', auth()->id())
            ->where('resource', $resource)
            ->orderBy('nama')
            ->get();
    }
}
