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
        $middleware->alias([
            'customer' => \App\Http\Middleware\EnsureCustomer::class,
        ]);

        // These are internal utility routes with no UI/form yet (exercised
        // via curl/Postman until the backoffice exists in Fase 2) — exempt
        // from CSRF so manual testing doesn't need a token dance. Revisit
        // once these are gated behind admin auth.
        $middleware->validateCsrfTokens(except: [
            'accurate/databases/select',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
