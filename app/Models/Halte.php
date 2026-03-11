<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Halte extends Model {
    use HasFactory;
    protected $fillable = [
        'nama_halte',
        'alamat',
        'latitude',
        'longitude',
    ];

    public function routes() {
        return $this->belongsToMany(Route::class, 'route_halte')->withPivot('urutan')->withTimestamps();
    }

    public function attendancesKe() {
        return $this->hasMany(Attendance::class, 'halte_naik_id');
    }

    public function studentBusDefaults() {
        return $this->hasMany(StudentBus::class);
    }
}
