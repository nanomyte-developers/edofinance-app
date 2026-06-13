<?php

use App\Console\Commands\ImportBudgetProgrammeCodes;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            // 'admin' => \App\Http\Middleware\AdminMiddleware::class,
            // 'customer' => \App\Http\Middleware\CustomerMiddleware::class,
            // 'user_role' => \App\Http\Middleware\UserRoleMiddleware::class,
            // 'check_role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withCommands([
        // Register your custom Artisan commands
        ImportBudgetProgrammeCodes::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
