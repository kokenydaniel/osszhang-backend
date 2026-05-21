<?php

namespace App\Support;

class MetersSettings
{
    public static function defaults(): array
    {
        return config('meters.defaults', []);
    }

    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        $location = trim((string) ($stored['default_location'] ?? $defaults['default_location']));
        if ($location === '') {
            $location = $defaults['default_location'];
        }

        $templates = [];
        foreach ($stored['templates'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $templates[] = [
                'name' => $name,
                'unit' => trim((string) ($row['unit'] ?? 'kWh')) ?: 'kWh',
                'location' => trim((string) ($row['location'] ?? $location)) ?: $location,
            ];
        }

        if (count($templates) === 0) {
            $templates = $defaults['templates'];
        }

        return [
            'default_location' => $location,
            'units' => BusinessSettings::normalizeList($stored['units'] ?? null, $defaults['units']),
            'templates' => $templates,
        ];
    }
}
