<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentService {
    public function getAllStudents($perPage = 15) {
        return Student::with('user')->paginate($perPage);
    }

    public function getStudentById($id) {
        return Student::with('user')->findOrFail($id);
    }

    public function getPendingStudents($perPage = 15) {
        return Student::where('approval_status', 'pending')->with('user')->paginate($perPage);
    }

    public function createStudent($data) {
        try {
            return DB::transaction(function () use ($data) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => 'siswa',
                    'api_token' => Str::random(60),
                ]);
                $student = Student::create([
                    'user_id' => $user->id,
                    'nis' => $data['nis'],
                    'sekolah' => $data['sekolah'],
                    'kelas' => $data['kelas'] ?? 'Belum ditentukan',
                    'alamat' => $data['alamat'],
                    'no_hp' => $data['no_hp'],
                    'approval_status' => 'approved', // admin create langsung approved
                ]);
                return [
                    'success' => true,
                    'user' => $user,
                    'student' => $student,
                ];
            });
        } catch (\Exception $e) {
            $message = 'Gagal membuat siswa';
            if (strpos($e->getMessage(), 'Integrity constraint violation') !== false) {
                if (strpos($e->getMessage(), 'nis_unique') !== false) {
                    $message = 'NIS sudah terdaftar';
                } elseif (strpos($e->getMessage(), 'no_hp_unique') !== false) {
                    $message = 'Nomor HP sudah terdaftar';
                } elseif (strpos($e->getMessage(), 'email_unique') !== false) {
                    $message = 'Email sudah terdaftar';
                }
            }
            return [
                'success' => false,
                'error' => $message,
            ];
        }
    }

    public function updateStudent($id, $data) {
        try {
            $student = Student::where('user_id', $id)->firstOrFail();
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
            return [
                'success' => true,
                'user' => $user->fresh(),
                'student' => $student->fresh(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal update siswa: ' . $e->getMessage(),
            ];
        }
    }

    public function deleteStudent($id) {
        try {
            $student = Student::where('user_id', $id)->firstOrFail();
            $userId = $student->user_id;
            $student->delete();
            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->delete();
                }
            }
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal menghapus siswa: ' . $e->getMessage(),
            ];
        }
    }

    public function approveStudent($id) {
        try {
            $student = Student::where('user_id', $id)->firstOrFail();
            if ($student->approval_status === 'approved') {
                return [
                    'success' => false,
                    'error' => 'Siswa sudah disetujui',
                ];
            }
            $student->approval_status = 'approved';
            $student->save();
            return [
                'success' => true,
                'student' => $student->load('user'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal approve siswa: ' . $e->getMessage(),
            ];
        }
    }

    public function rejectStudent($id, $reason) {
        try {
            $student = Student::where('user_id', $id)->firstOrFail();
            if ($student->approval_status !== 'pending') {
                return [
                    'success' => false,
                    'error' => 'Hanya siswa dengan status pending yang dapat ditolak',
                ];
            }
            $student->approval_status = 'rejected';
            $student->rejection_reason = $reason;
            $student->save();
            return [
                'success' => true,
                'student' => $student->load('user'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal reject siswa: ' . $e->getMessage(),
            ];
        }
    }

    public function generateBarcodeText($studentId) {
        $student = Student::findOrFail($studentId);
        return $student->nis ?: ('STU-' . $student->id);
    }
}
