<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model {
    use HasFactory;
    protected $fillable = [
        'kode_bus',
        'plat_nomor',
        'status',
        'photo',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string {
        if (!$this->photo) return null;
        // Foto disimpan di public/images/buses/ (tanpa symlink storage)
        // URL: http://host/images/buses/xxx.jpg
        if (str_starts_with($this->photo, 'http')) {
            return $this->photo;
        }
        return asset($this->photo);
    }

    public function drivers() {
        return $this->belongsToMany(Driver::class, 'bus_driver')->withPivot('tanggal_mulai', 'tanggal_selesai')->withTimestamps();
    }

    public function gpsTracks() {
        return $this->hasMany(GpsTrack::class);
    }

    public function attendances() {
        return $this->hasMany(Attendance::class);
    }

    public function dailyReports() {
        return $this->hasMany(DailyReport::class);
    }

    public function students() {
        return $this->belongsToMany(Student::class, 'student_bus')->withPivot('halte_id')->withTimestamps();
    }

    public function routes() {
        return $this->hasMany(Route::class);
    }

    public function driver(){
        return $this->hasOneThrough(
            Driver::class,
            BusDriver::class,
            'bus_id',
            'id',
            'id',
            'driver_id'
        );
    }

    public function busDrivers(){
        return $this->hasMany(BusDriver::class, 'bus_id');
    }
}