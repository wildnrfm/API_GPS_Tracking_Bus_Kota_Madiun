<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsDriver {
    public function handle(Request $request, Closure $next) {
        $user = $request->user();
        if (! $user || $user->role !== 'driver') {
            return response()->json(['message' => 'Forbidden: drivers only'], 403);
        }
        return $next($request);
    }
}
