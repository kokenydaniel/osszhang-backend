<?php

namespace App\Support;

class SavingsSettings
{
    public static function defaults(): array
    {
        return config('savings.defaults', []);
    }

    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        $owners = BusinessSettings::normalizeList($stored['owners'] ?? null, $defaults['owners']);
        $defaultOwner = trim((string) ($stored['default_owner'] ?? $defaults['default_owner']));
        if ($defaultOwner !== '' && ! in_array($defaultOwner, $owners, true)) {
            $defaultOwner = $owners[0] ?? '';
        } elseif ($defaultOwner === '' && count($owners) > 0) {
            $defaultOwner = $owners[0];
        }

        $separateOwner = trim((string) ($stored['separate_owner'] ?? $defaults['separate_owner']));

        return [
            'owners' => $owners,
            'default_owner' => $defaultOwner,
            'separate_owner' => $separateOwner,
            'currencies' => BusinessSettings::normalizeList($stored['currencies'] ?? null, $defaults['currencies']),
            'default_count_in_savings' => array_key_exists('default_count_in_savings', $stored)
                ? (bool) $stored['default_count_in_savings']
                : (bool) $defaults['default_count_in_savings'],
        ];
    }
}
