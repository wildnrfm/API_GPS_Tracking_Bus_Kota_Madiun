<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Student extends Model {
    use HasFactory;
    protected $fillable = [
        'user_id',
        'nis',
        'sekolah',
        'kelas',
        'alamat',
        'no_hp',
        'approval_status',
        'status',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function buses() {
        return $this->belongsToMany(Bus::class, 'student_bus')->withPivot('halte_id')->withTimestamps();
    }

    /**
     * Halte penjemputan siswa — diambil dari pivot student_bus.halte_id
     * Shortcut agar tidak perlu join manual setiap saat.
     */
    public function halte() {
        return $this->hasOneThrough(
            Halte::class,
            StudentBus::class,
            'student_id', // FK di student_bus
            'id',         // PK di haltes
            'id',         // PK di students
            'halte_id'    // FK di student_bus -> haltes
        );
    }

    /**
     * Accessor: bus aktif pertama yang di-assign ke siswa ini.
     * Dipakai untuk QR card di admin & response API.
     */
    public function getActiveBusAttribute() {
        return $this->buses()->where('status', 'aktif')->first();
    }

    public function attendance() {
        return $this->hasMany(Attendance::class);
    }
}