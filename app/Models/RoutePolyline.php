<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoutePolyline extends Model {
    use HasFactory;

    protected $fillable = [
        'route_id',
        'latitude',
        'longitude',
        'urutan',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
        'urutan'    => 'integer',
    ];

    public function route() {
        return $this->belongsTo(Route::class);
    }
}
