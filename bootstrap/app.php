<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        // Laravel's default JSON exception rendering includes exception/file/
        // trace keys whenever APP_DEBUG=true — that leaks internal file paths
        // and stack traces to any API consumer, not just local dev tooling.
        // Force a clean {message} body for every JSON error response instead,
        // regardless of debug mode; the full trace still lands in
        // storage/logs/laravel.log via Laravel's normal exception logging.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return null; // already renders a clean {message, errors} body
            }

            $status = match (true) {
                $e instanceof AuthenticationException => 401,
                $e instanceof ModelNotFoundException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            $message = $status === 500 && ! config('app.debug')
                ? 'Terjadi kesalahan pada server.'
                : ($e->getMessage() ?: 'Terjadi kesalahan.');

            return response()->json(['message' => $message], $status);
        });
    })->create();
