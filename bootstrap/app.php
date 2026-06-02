<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsDriver;
use App\Http\Middleware\EnsureUserIsStudent;
use App\Http\Middleware\CheckTokenExpiration;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\SanitizeInput;

return Application::configure(basePath: dirname(__DIR__))->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)->withMiddleware(function (Middleware $middleware): void {
    // CORS ditangani HANYA di public/index.php (level PHP native).
    // Hapus HandleCors bawaan Laravel agar tidak duplikat CORS headers.
    $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);
    $middleware->prepend(SetSecurityHeaders::class);
    $middleware->prepend(SanitizeInput::class);
    $middleware->alias([
        'admin' => EnsureUserIsAdmin::class,
        'driver' => EnsureUserIsDriver::class,
        'student' => EnsureUserIsStudent::class,
        'check.token.expiration' => CheckTokenExpiration::class,
    ]);
})->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (NotFoundHttpException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Resource not found',
                'status' => 404
            ], 404);
        }
    });
})->create();
