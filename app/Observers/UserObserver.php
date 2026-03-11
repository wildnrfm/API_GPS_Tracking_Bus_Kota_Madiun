<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Student;

class UserObserver {
    public function updating(User $user): void {
        if ($user->isDirty('role')) {
            $oldRole = $user->getOriginal('role');
            $newRole = $user->role;
            if ($oldRole === 'siswa' && $newRole !== 'siswa') {
                $student = Student::where('user_id', $user->id)->first();
                if ($student) {
                    $student->update(['status' => 'inactive']);
                }
            }
            if ($oldRole !== 'siswa' && $newRole === 'siswa') {
                $student = Student::where('user_id', $user->id)->first();
                if ($student) {
                    $student->update(['status' => 'active']);
                }
            }
        }
    }

    public function deleting(User $user): void {
        $student = Student::where('user_id', $user->id)->first();
        if ($student) {
            $student->update(['status' => 'archived']);
        }
    }
}
