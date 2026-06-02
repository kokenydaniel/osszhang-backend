<?php

namespace App\Support;

class UtilitiesSettings
{
    public static function defaults(): array
    {
        return config('utilities.module_defaults', []);
    }

    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        $payerId = $stored['default_payer_user_id'] ?? $defaults['default_payer_user_id'];
        $payerId = $payerId === null || $payerId === '' ? null : (int) $payerId;
        if ($payerId !== null && $payerId <= 0) {
            $payerId = null;
        }

        return [
            'clone_from_previous_month' => array_key_exists('clone_from_previous_month', $stored)
                ? (bool) $stored['clone_from_previous_month']
                : (bool) $defaults['clone_from_previous_month'],
            'settlement_auto_suggest' => array_key_exists('settlement_auto_suggest', $stored)
                ? (bool) $stored['settlement_auto_suggest']
                : (bool) $defaults['settlement_auto_suggest'],
            'default_payer_user_id' => $payerId,
        ];
    }
}
