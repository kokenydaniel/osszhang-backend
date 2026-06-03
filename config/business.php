<?php

return [
    'defaults' => [
        'channels' => [],
        'payment_methods' => [],
        'providers' => [],
        'destinations' => [],
        'default_vat_percent' => 27,
        'price_input_mode' => 'gross',
        'order_statuses' => ['Függőben', 'Feldolgozás alatt', 'Szállítva', 'Teljesítve', 'Visszautaltva'],
        'order_status_colors' => [
            'Függőben' => 'warning',
            'Feldolgozás alatt' => 'info',
            'Szállítva' => 'primary',
            'Teljesítve' => 'success',
            'Visszautaltva' => 'danger',
        ],
        'shopify_sync_schedule' => 'off',
        'shopify_last_synced_at' => null,
        'sumup_last_synced_at' => null,
        'tax_regime' => 'aam',
        'income_tax_method' => 'cost_ratio',
        'cost_ratio_percent' => 40,
        'revenue_basis' => 'documented_only',
    ],
];
