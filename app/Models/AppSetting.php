<?php
// app/Models/AppSetting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $table = 'app_settings';
    protected $fillable = ['key', 'value', 'type', 'group', 'label', 'description'];
    
    public static function get(string $key, $default = null)
    {
        return self::getValue($key, $default);
    }
    
    public static function getValue(string $key, $default = null)
    {
        $settings = Cache::remember('app_settings', 3600, function () {
            return self::all()->keyBy('key');
        });
        
        $setting = $settings->get($key);
        
        if (!$setting) {
            return $default;
        }
        
        return match($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }
    
    public static function setValue(string $key, $value, string $type = 'string'): void
    {
        $valueToStore = match($type) {
            'json' => is_string($value) ? $value : json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
        
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $valueToStore, 'type' => $type]
        );
        
        self::clearCache();
    }
    
    public static function clearCache(): void
    {
        Cache::forget('app_settings');
    }
}
