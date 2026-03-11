<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CheckTokenExpiration {
    public function handle(Request $request, Closure $next) {
        if ($request->user()) {
            $user = $request->user();
            if ($user->token_expires_at && Carbon::parse($user->token_expires_at)->isPast()) {
                $user->api_token = null;
                $user->token_expires_at = null;
                $user->save();
                return response()->json([
                    'success' => false,
                    'message' => 'Token sudah kadaluarsa. Silakan login kembali.',
                    'code' => 'TOKEN_EXPIRED'
                ], 401);
            }
            if ($user->is_suspended) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda telah disuspend oleh administrator.',
                    'code' => 'ACCOUNT_SUSPENDED'
                ], 403);
            }
        }
        return $next($request);
    }
}
