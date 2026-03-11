<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model {
    use HasFactory;
    protected $fillable = [
        'bus_id',
        'nama_rute',
    ];

    public function bus() {
        return $this->belongsTo(Bus::class);
    }

    public function haltes() {
        return $this->belongsToMany(Halte::class, 'route_halte')->withPivot('urutan')->withTimestamps();
    }
}
