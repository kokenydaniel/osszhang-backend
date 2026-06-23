<?php

use Laravel\Cashier\Console\WebhookCommand;
use Laravel\Cashier\Invoices\DompdfInvoiceRenderer;

return [

    'key' => env('STRIPE_KEY'),

    'secret' => env('STRIPE_SECRET'),

    'path' => env('CASHIER_PATH', 'stripe'),

    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
        'events' => WebhookCommand::DEFAULT_EVENTS,
    ],

    'currency' => env('CASHIER_CURRENCY', 'usd'),

    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'en'),

    'payment_notification' => env('CASHIER_PAYMENT_NOTIFICATION'),

    'invoices' => [

        'renderer' => env('CASHIER_INVOICE_RENDERER', DompdfInvoiceRenderer::class),

        'options' => [

            'paper' => env('CASHIER_PAPER', 'letter'),

            'remote_enabled' => env('CASHIER_REMOTE_ENABLED', false),
        ],
    ],

    'logger' => env('CASHIER_LOGGER'),

];
