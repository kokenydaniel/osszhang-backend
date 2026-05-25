<?php

use App\Support\AccessControl;

return [

    'prices' => [
        'pro_monthly' => env('STRIPE_PRICE_PRO_MONTHLY', 'price_1Tb0mQ4JTKO17UMN7W7qMq5v'),
        'pro_yearly' => env('STRIPE_PRICE_PRO_YEARLY', 'price_1Tb0qn4JTKO17UMNZGf86Qeg'),
        'premium_monthly' => env('STRIPE_PRICE_PREMIUM_MONTHLY', 'price_1Tb0p34JTKO17UMNbxqQPZv0'),
        'premium_yearly' => env('STRIPE_PRICE_PREMIUM_YEARLY', 'price_1Tb0rN4JTKO17UMNcKBgy6S8'),
    ],

    'price_tiers' => [
        env('STRIPE_PRICE_PRO_MONTHLY', 'price_1Tb0mQ4JTKO17UMN7W7qMq5v') => AccessControl::TIER_PRO,
        env('STRIPE_PRICE_PRO_YEARLY', 'price_1Tb0qn4JTKO17UMNZGf86Qeg') => AccessControl::TIER_PRO,
        env('STRIPE_PRICE_PREMIUM_MONTHLY', 'price_1Tb0p34JTKO17UMNbxqQPZv0') => AccessControl::TIER_PREMIUM,
        env('STRIPE_PRICE_PREMIUM_YEARLY', 'price_1Tb0rN4JTKO17UMNcKBgy6S8') => AccessControl::TIER_PREMIUM,
    ],

];
