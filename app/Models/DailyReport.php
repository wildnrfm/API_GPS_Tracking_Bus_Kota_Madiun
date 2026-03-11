<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyReport extends Model {
    use HasFactory;
    protected $fillable = [
        'bus_id',
        'tanggal',
        'bus_driver_id',
        'catatan_driver',
        'total_penumpang',
    ];

    public function bus() {
        return $this->belongsTo(Bus::class);
    }

    public function busDriver() {
        return $this->belongsTo(BusDriver::class, 'bus_driver_id');
    }
}
