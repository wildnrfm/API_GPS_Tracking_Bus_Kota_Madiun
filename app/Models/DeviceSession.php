<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceSession extends Model
{
    protected $table = 'device_sessions';
    protected $guarded = [];
    protected $dates = ['created_at', 'expires_at', 'last_activity_at', 'updated_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired()
    {
        return now()->isAfter($this->expires_at);
    }
}
