<?php

use App\Http\Middleware\ApplySeoRobots;
use App\Http\Middleware\EnsureAdminAuthenticated;
use App\Http\Middleware\RedirectIfAdminAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.auth' => EnsureAdminAuthenticated::class,
            'admin.guest' => RedirectIfAdminAuthenticated::class,
        ]);

        $middleware->web(append: [
            ApplySeoRobots::class,
        ]);

        $middleware->redirectGuestsTo(function () {
            return config('vtt.seo') ? route('login') : '/';
        });
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
