<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['key', 'value', 'type', 'group', 'label'];

    private const CACHE_KEY = 'system_settings_all';
    private const CACHE_TTL = 3600;

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::allCached();
        $setting  = $settings[$key] ?? null;

        if ($setting === null) return $default;

        return static::cast($setting['value'], $setting['type']);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
        );
        Cache::forget(static::CACHE_KEY);
    }

    public static function allCached(): array
    {
        return Cache::remember(static::CACHE_KEY, static::CACHE_TTL, function () {
            return static::all()->keyBy('key')->map(fn ($s) => [
                'value' => $s->value,
                'type'  => $s->type,
            ])->toArray();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget(static::CACHE_KEY);
    }

    public static function byGroup(string $group): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('group', $group)->orderBy('key')->get();
    }

    private static function cast(mixed $value, string $type): mixed
    {
        return match($type) {
            'boolean' => in_array($value, ['1', 'true', true], true),
            'integer' => (int) $value,
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }
}
