<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Student;
use App\Models\User;

class StudentRejectionHistory extends Model {
    use HasFactory;

    protected $fillable = [
        'student_id',
        'user_id',
        'name',
        'email',
        'nis',
        'sekolah',
        'kelas',
        'alamat',
        'no_hp',
        'rejected_by',
        'reason',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function rejectedBy() {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
