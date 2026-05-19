<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\DeviceSession;

class CheckTokenExpiration {
    public function handle(Request $request, Closure $next) {
        if ($request->user()) {
            $user = $request->user();
            
            // Validasi device session dari token
            $token = $request->bearerToken();
            if ($token) {
                $deviceSession = DeviceSession::where('api_token', $token)->first();
                
                if (!$deviceSession) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Token tidak valid. Silakan login kembali.',
                        'code' => 'INVALID_TOKEN'
                    ], 401);
                }
                
                if ($deviceSession->isExpired()) {
                    $deviceSession->delete();
                    return response()->json([
                        'success' => false,
                        'message' => 'Token sudah kadaluarsa. Silakan login kembali.',
                        'code' => 'TOKEN_EXPIRED'
                    ], 401);
                }
                
                // Update last activity
                $deviceSession->update(['last_activity_at' => now()]);
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
