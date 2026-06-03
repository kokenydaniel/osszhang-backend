<?php

return [
    'defaults' => [
        'currencies' => ['HUF', 'EUR'],
        'default_currency' => 'HUF',
        'members' => [],
        'interest_enabled' => false,
        'interest_rate_percent' => 10,
        /** balance = teljes egyenleg; month_allowance = csak a hónapban kiosztott zsebpénz */
        'interest_on' => 'balance',
        /** no_expense = csak ha nem költött; remaining = bent maradt részre (költés után is) */
        'interest_basis' => 'no_expense',
    ],
];
