<?php

return [
    'defaults' => [
        'default_strategy' => 'avalanche',
        'default_extra_monthly' => 0,
        'pay_add_to_budget_default' => true,
        'payment_category_pattern' => 'hitel|tartoz|törleszt',
        'reminder_days_before' => 3,
        'default_interest_rate_annual' => 0,
        'debt_type_templates' => [
            ['label' => 'Lakáshitel', 'default_interest_rate_annual' => 5],
            ['label' => 'Autóhitel', 'default_interest_rate_annual' => 8],
            ['label' => 'Hitelkártya', 'default_interest_rate_annual' => 18],
            ['label' => 'Személyi kölcsön', 'default_interest_rate_annual' => 12],
        ],
    ],
];
