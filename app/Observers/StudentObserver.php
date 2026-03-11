<?php

namespace App\Observers;

use App\Models\Student;
use App\Models\Attendance;

class StudentObserver {
    public function deleting(Student $student): void {
        $student->buses()->detach();
        Attendance::where('student_id', $student->id)->delete();
    }

    public function restoring(Student $student): void {
        Attendance::where('student_id', $student->id)->restore();
    }
}
