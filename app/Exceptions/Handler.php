<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {});
    }

    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource tidak ditemukan',
                'status'  => 404,
            ], 404);
        }

        if ($exception instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors'  => $exception->errors(),
            ], 422);
        }

        if (!$request->expectsJson()) {
            return parent::render($request, $exception);
        }

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan pada server',
            'error'   => config('app.debug') ? $exception->getMessage() : 'Internal server error',
        ], 500);
    }
}