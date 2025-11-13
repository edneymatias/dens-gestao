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
        // Register InitializeLocale middleware for web and api groups
        // This runs after tenancy and session initialization
        $middleware->web(append: [
            \App\Http\Middleware\InitializeLocale::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\InitializeLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
