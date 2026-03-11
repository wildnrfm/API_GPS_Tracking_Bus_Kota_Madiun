<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanitizeInput {
    private $skipSanitization = [
        'password',
        'password_confirmation',
        'api_token',
        'token',
        'secret',
    ];
    public function handle(Request $request, Closure $next) {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            return $next($request);
        }
        $input = $request->all();
        $input = $this->sanitizeArray($input);
        $request->merge($input);
        return $next($request);
    }

    private function sanitizeArray($data) {
        return collect($data)->map(function ($value, $key) {
            if (in_array(strtolower($key), array_map('strtolower', $this->skipSanitization))) {
                return $value;
            }
            if (is_array($value)) {
                return $this->sanitizeArray($value);
            }
            if (is_string($value)) {
                return $this->sanitizeString($value);
            }
            return $value;
        })->all();
    }

    private function sanitizeString($value) {
        $value = str_replace(chr(0), '', $value);
        $value = trim($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
        return $value;
    }
}
