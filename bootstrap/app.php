<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.access' => \App\Http\Middleware\EnsureTenantAccess::class,
            'tenant.module' => \App\Http\Middleware\EnsureTenantModuleAccess::class,
        ]);

        // Stripe chiama questo indirizzo (impostato nella Dashboard prima di /api/stripe/webhook): niente token CSRF, la firma basta.
        $middleware->validateCsrfTokens(except: ['stripe/billing/webhook']);

        // Condivisione fluttuante e discreta su tutte le pagine pubbliche (HTML indicizzabili), senza toccare le viste.
        $middleware->web(append: [\App\Http\Middleware\InjectFloatingShare::class]);

        $middleware->redirectGuestsTo(fn (Request $request) => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
