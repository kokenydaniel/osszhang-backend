<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'stripe/*',
        ]);

        $middleware->alias([
            'household.editor' => \App\Http\Middleware\EnsureHouseholdEditor::class,
            'premium.ai' => \App\Http\Middleware\EnsurePremiumAiFeature::class,
            'platform.admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'platform.feature' => \App\Http\Middleware\EnsurePlatformFeature::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\CheckMaintenanceMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
