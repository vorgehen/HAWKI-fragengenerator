<?php

use Illuminate\Http\Request;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Services\Storage\StorageServiceFactory;
use App\Services\Storage\DefaultStorageService;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\AvatarStorageService;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');
    })

    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->expectsJson() || $request->is('api/*');
        });
    })->withSingletons([
        DefaultStorageService::class => fn (Application $app) => $app->make(StorageServiceFactory::class)->getDefaultStorage(),
        FileStorageService::class => fn (Application $app) => $app->make(StorageServiceFactory::class)->getFileStorage(),
        AvatarStorageService::class => fn (Application $app) => $app->make(StorageServiceFactory::class)->getAvatarStorage(),
    ])->create();
