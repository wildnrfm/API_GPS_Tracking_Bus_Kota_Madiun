<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model {
    protected $fillable = [
        'user_id',
        'action',
        'model',
        'model_id',
        'changes',
        'ip_address',
        'user_agent',
        'status',
        'description',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public static function log($action, $user_id = null, $data = []) {
        if ($user_id !== null && !\App\Models\User::where('id', $user_id)->exists()) {
            $user_id = null;
        }
        return self::create([
            'user_id' => $user_id,
            'action' => $action,
            'model' => $data['model'] ?? null,
            'model_id' => $data['model_id'] ?? null,
            'changes' => $data['changes'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'status' => $data['status'] ?? 'success',
            'description' => $data['description'] ?? null,
        ]);
    }

    public static function forUser($user_id) {
        return self::where('user_id', $user_id)->latest();
    }

    public static function forAction($action) {
        return self::where('action', $action)->latest();
    }

    public static function recent($days = 7) {
        return self::where('created_at', '>=', now()->subDays($days))->latest();
    }
}
