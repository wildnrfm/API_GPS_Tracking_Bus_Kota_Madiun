<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model {
    use HasFactory;
    protected $fillable = [
        'user_id',
        'nik',
        'no_hp',
        'alamat',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function buses() {
        return $this->belongsToMany(Bus::class, 'bus_driver')->withPivot('tanggal_mulai', 'tanggal_selesai', 'gps_status', 'last_gps_update')->withTimestamps();
    }
}
