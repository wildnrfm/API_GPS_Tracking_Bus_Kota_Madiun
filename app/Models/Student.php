<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function attendance() {
        return $this->hasMany(Attendance::class);
    }
}
