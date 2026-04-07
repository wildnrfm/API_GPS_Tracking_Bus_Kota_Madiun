<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GpsTrack extends Model {
    use HasFactory;
    protected $fillable = [
        'bus_id',
        'latitude',
        'longitude',
        'speed',
        'accuracy',
        'heading',
        'device_timestamp',
        'recorded_at',
    ];

    public function bus() {
        return $this->belongsTo(Bus::class);
    }
}