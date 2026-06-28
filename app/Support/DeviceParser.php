<?php

namespace App\Support;

/**
 * Lightweight User-Agent parser (no dependency) → a friendly label like
 * "Chrome on Windows · Desktop" for the active-devices list.
 */
class DeviceParser
{
    public static function describe(?string $ua): array
    {
        $ua = (string) $ua;

        $browser = self::match($ua, [
            'Edg' => 'Edge',
            'OPR' => 'Opera',
            'Opera' => 'Opera',
            'Chrome' => 'Chrome',
            'CriOS' => 'Chrome',
            'Firefox' => 'Firefox',
            'FxiOS' => 'Firefox',
            'Safari' => 'Safari',
        ], 'Unknown browser');

        // Resolve OS / device.
        $os = 'Unknown OS';
        $type = 'Desktop';
        if (preg_match('/iPhone/i', $ua)) { $os = 'iPhone'; $type = 'Mobile'; }
        elseif (preg_match('/iPad/i', $ua)) { $os = 'iPad'; $type = 'Tablet'; }
        elseif (preg_match('/Android/i', $ua)) { $os = 'Android'; $type = preg_match('/Mobile/i', $ua) ? 'Mobile' : 'Tablet'; }
        elseif (preg_match('/Windows/i', $ua)) { $os = 'Windows'; }
        elseif (preg_match('/Mac OS X|Macintosh/i', $ua)) { $os = 'macOS'; }
        elseif (preg_match('/Linux/i', $ua)) { $os = 'Linux'; }

        $label = trim("$browser on $os");

        return [
            'browser' => $browser,
            'os' => $os,
            'type' => $type,           // Mobile | Tablet | Desktop
            'label' => $label,         // "Chrome on Windows"
        ];
    }

    private static function match(string $ua, array $map, string $default): string
    {
        foreach ($map as $needle => $name) {
            if (stripos($ua, $needle) !== false) {
                return $name;
            }
        }

        return $default;
    }
}
