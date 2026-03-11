<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteHalte extends Model {
    use HasFactory;
    protected $table = 'route_halte';
    protected $fillable = [
        'route_id',
        'halte_id',
        'urutan',
    ];

    public function route() {
        return $this->belongsTo(Route::class);
    }

    public function halte() {
        return $this->belongsTo(Halte::class);
    }
}
