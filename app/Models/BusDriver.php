<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusDriver extends Model {
    use HasFactory;
    protected $table = 'bus_driver';
    protected $fillable = [
        'bus_id',
        'driver_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'gps_status',
        'last_gps_update',
    ];

    public function bus() {
        return $this->belongsTo(Bus::class);
    }

    public function driver() {
        return $this->belongsTo(Driver::class);
    }

    public function scopeActive($query) {
        return $query->where(function ($q) {
            $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', now()->toDateString());
        });
    }

    public function scopeExpired($query) {
        return $query->where('tanggal_selesai', '<', now()->toDateString())->whereNotNull('tanggal_selesai');
    }
}
