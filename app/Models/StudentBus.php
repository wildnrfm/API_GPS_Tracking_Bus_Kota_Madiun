<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentBus extends Model {
    use HasFactory;
    protected $table = 'student_bus';
    protected $fillable = [
        'student_id',
        'bus_id',
        'halte_id',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function bus() {
        return $this->belongsTo(Bus::class);
    }

    public function halte() {
        return $this->belongsTo(Halte::class);
    }
}
