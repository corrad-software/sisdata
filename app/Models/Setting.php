<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected static string $cacheKey = 'settings.all';

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allCached()->get($key, $default);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(static::$cacheKey);
    }

    public static function allCached(): Collection
    {
        return Cache::rememberForever(static::$cacheKey, function () {
            return static::query()->pluck('value', 'key');
        });
    }
}
