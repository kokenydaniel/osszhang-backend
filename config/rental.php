<?php

return [
    'defaults' => [
        'currencies' => ['HUF', 'EUR'],
        'default_currency' => 'HUF',
        'contract_reminder_days_before' => 60,
        'overdue_grace_days' => 0,
        'budget_sync_default' => false,
        'income_category_pattern' => 'bérlet',
    ],
    'expense_types' => [
        'maintenance' => 'Karbantartás, javítás',
        'renovation' => 'Felújítás',
        'common_cost' => 'Közös költség (társasház)',
        'utilities' => 'Közmű / rezsi (tulajdonos fizeti)',
        'insurance' => 'Biztosítás',
        'tax' => 'Adó, közteher',
        'other' => 'Egyéb tulajdonosi költség',
    ],
];
