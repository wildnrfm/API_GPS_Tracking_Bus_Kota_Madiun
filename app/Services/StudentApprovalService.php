<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentApprovalService {
    public function createStudent(array $studentData, array $userData){
        try {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'role' => 'siswa',
                'api_token' => Str::random(60),
            ]);
            $student = Student::create([
                'user_id' => $user->id,
                'nis' => $studentData['nis'],
                'sekolah' => $studentData['sekolah'],
                'kelas' => $studentData['kelas'] ?? 'Belum ditentukan',
                'alamat' => $studentData['alamat'],
                'no_hp' => $studentData['no_hp'],
                'approval_status' => $studentData['approval_status'] ?? 'pending',
            ]);
            return $student->load('user');
        } catch (\Exception $e) {
            throw new \Exception('Failed to create student: ' . $e->getMessage());
        }
    }

    public function updateStudent(Student $student, array $data){
        try {
            $user = $student->user;
            $userFields = [];
            $studentFields = [];
            foreach ($data as $key => $value) {
                if (in_array($key, ['name', 'email', 'password'])) {
                    $userFields[$key] = $value;
                } else {
                    $studentFields[$key] = $value;
                }
            }
            if (!empty($userFields)) {
                if (isset($userFields['password'])) {
                    $userFields['password'] = Hash::make($userFields['password']);
                }
                $user->update($userFields);
            }
            if (!empty($studentFields)) {
                $student->update($studentFields);
            }
            return $student->refresh()->load('user');
        } catch (\Exception $e) {
            throw new \Exception('Failed to update student: ' . $e->getMessage());
        }
    }

    public function approveStudent(Student $student) {
        if ($student->approval_status === 'approved') {
            throw new \Exception('Siswa sudah disetujui sebelumnya');
        }
        try {
            $student->approval_status = 'approved';
            $student->save();
            return $student->load('user');
        } catch (\Exception $e) {
            throw new \Exception('Gagal mesetujui siswa: ' . $e->getMessage());
        }
    }

    public function rejectStudent(Student $student, string $reason){
        if ($student->approval_status !== 'pending') {
            throw new \Exception('Hanya siswa dengan status pending yang dapat ditolak');
        }
        try {
            $student->approval_status = 'rejected';
            $student->rejection_reason = $reason;
            $student->save();
            return $student->load('user');
        } catch (\Exception $e) {
            throw new \Exception('Gagal menolak siswa: ' . $e->getMessage());
        }
    }

    public function deleteStudent(Student $student){
        try {
            $userId = $student->user_id;
            $student->delete();
            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->delete();
                }
            }
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Failed to delete student: ' . $e->getMessage());
        }
    }

    public function getPendingStudents($perPage = 15) {
        return Student::where('approval_status', 'pending')->with('user')->paginate($perPage);
    }

    public function getApprovedStudents($perPage = 15){
        return Student::where('approval_status', 'approved')->with('user')->paginate($perPage);
    }

    public function getRejectedStudents($perPage = 15){
        return Student::where('approval_status', 'rejected')->with('user')->paginate($perPage);
    }
}
