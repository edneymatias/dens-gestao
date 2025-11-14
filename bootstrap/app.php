<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register web middleware that depends on central session state
        $middleware->web(append: [
            \App\Http\Middleware\InitializeTenancyBySession::class,
            \App\Http\Middleware\InitializeLocale::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\InitializeLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
