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
        'shopify_sync_schedule' => 'off',
        'shopify_last_synced_at' => null,
    ],
];
