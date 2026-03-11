<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model {
    use HasFactory;
    protected $table = 'attendance';
    protected $fillable = [
        'qr_id',
        'student_id',
        'bus_id',
        'halte_naik_id',
        'tanggal',
        'waktu_naik',
        'waktu_turun',
        'lat_naik',
        'lng_naik',
        'lat_turun',
        'lng_turun',
        'status',
        'qr_expires_at',
    ];
    protected $casts = [
        'tanggal' => 'date',
        'waktu_naik' => 'datetime',
        'waktu_turun' => 'datetime',
        'qr_expires_at' => 'datetime',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function bus() {
        return $this->belongsTo(Bus::class);
    }

    public function halteNaik() {
        return $this->belongsTo(Halte::class, 'halte_naik_id');
    }

    public static function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2) +cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;
        return round($distance, 2);
    }
}
