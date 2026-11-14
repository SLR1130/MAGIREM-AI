<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SystemSetting extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("sys_setting_{$key}", 86400, function () use ($key, $default) {
            try {
                if (!Schema::hasTable('system_settings')) {
                    return $default;
                }
                $record = static::query()->find($key);
                if (!$record) {
                    return $default;
                }
                $decoded = json_decode((string) $record->value, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : $record->value;
            } catch (\Throwable $e) {
                return $default;
            }
        });
    }

    public static function set(string $key, $value): void
    {
        try {
            if (Schema::hasTable('system_settings')) {
                static::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => json_encode($value)]
                );
            }
        } catch (\Throwable $e) {
            // Log or fallback
        }
        Cache::forever("sys_setting_{$key}", $value);
    }
}
