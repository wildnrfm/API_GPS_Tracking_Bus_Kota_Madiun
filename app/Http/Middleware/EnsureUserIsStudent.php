<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsStudent {
    public function handle(Request $request, Closure $next) {
        $user = $request->user();
        if (! $user || $user->role !== 'siswa') {
            return response()->json(['message' => 'Forbidden: students only'], 403);
        }
        return $next($request);
    }
}
