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

        $locationGroups = array_key_exists('location_groups', $stored)
            ? self::normalizeLocationGroups($stored['location_groups'])
            : ($defaults['location_groups'] ?? []);

        $reminderDay = (int) ($stored['reading_reminder_day'] ?? $defaults['reading_reminder_day']);
        $reminderDay = max(0, min(28, $reminderDay));

        $alertPercent = (int) ($stored['consumption_alert_percent'] ?? $defaults['consumption_alert_percent']);
        $alertPercent = max(0, min(200, $alertPercent));

        return [
            'default_location' => $location,
            'units' => BusinessSettings::normalizeList($stored['units'] ?? null, $defaults['units']),
            'templates' => $templates,
            'reading_reminder_day' => $reminderDay,
            'consumption_alert_percent' => $alertPercent,
            'show_annual_summary_on_dashboard' => array_key_exists('show_annual_summary_on_dashboard', $stored)
                ? (bool) $stored['show_annual_summary_on_dashboard']
                : (bool) $defaults['show_annual_summary_on_dashboard'],
            'location_groups' => $locationGroups,
        ];
    }

    /** @param  mixed  $rows */
    private static function normalizeLocationGroups($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $groups = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $locations = [];
            foreach ($row['locations'] ?? [] as $loc) {
                $l = trim((string) $loc);
                if ($l !== '') {
                    $locations[] = $l;
                }
            }
            if (count($locations) === 0) {
                $locations = [$name];
            }
            $groups[] = [
                'name' => $name,
                'locations' => array_values(array_unique($locations)),
            ];
        }

        return $groups;
    }
}
