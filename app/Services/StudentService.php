<?php

namespace App\Services;

use App\Mail\StudentApprovedMail;
use App\Mail\StudentRejectedMail;
use App\Models\Student;
use App\Models\User;
use App\Traits\CreatesUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StudentService {
    use CreatesUser;

    public function getAllStudents($perPage = 15) {
        return Student::with(['user', 'buses.routes', 'halte'])->paginate($perPage);
    }

    public function getStudentById($id) {
        return Student::with(['user', 'buses.routes', 'halte'])->findOrFail($id);
    }

    public function getPendingStudents($perPage = 15) {
        return Student::where('approval_status', 'pending')
            ->with(['user', 'buses.routes', 'halte'])
            ->paginate($perPage);
    }

    public function createStudent($data) {
        try {
            return DB::transaction(function () use ($data) {
                $user    = $this->createUser('siswa', $data);
                $student = Student::create([
                    'user_id'         => $user->id,
                    'nis'             => $data['nis'],
                    'sekolah'         => $data['sekolah'],
                    'kelas'           => $data['kelas'] ?? 'Belum ditentukan',
                    'alamat'          => $data['alamat'],
                    'no_hp'           => $data['no_hp'],
                    'approval_status' => 'approved', // admin create langsung approved
                ]);
                return [
                    'success' => true,
                    'user'    => $user,
                    'student' => $student,
                ];
            });
        } catch (\Exception $e) {
            $message = $this->parseDuplicateError($e, 'Gagal membuat siswa', [
                'nis_unique'   => 'NIS sudah terdaftar',
                'no_hp_unique' => 'Nomor HP sudah terdaftar',
                'email_unique' => 'Email sudah terdaftar',
            ]);
            return ['success' => false, 'error' => $message];
        }
    }

    public function updateStudent($id, $data) {
        try {
            $student = Student::where('user_id', $id)->firstOrFail();
            $this->updateUserAndProfile($student->user, $student, $data);
            return [
                'success' => true,
                'user'    => $student->user->fresh(),
                'student' => $student->fresh(),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal update siswa: ' . $e->getMessage()];
        }
    }

    public function deleteStudent($id) {
        try {
            $student = Student::where('user_id', $id)->firstOrFail();
            $userId  = $student->user_id;
            $student->delete();
            if ($userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->delete();
                }
            }
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal menghapus siswa: ' . $e->getMessage()];
        }
    }

    public function approveStudent($id) {
        try {
            $student = Student::where('user_id', $id)->firstOrFail();
            if ($student->approval_status === 'approved') {
                return ['success' => false, 'error' => 'Siswa sudah disetujui'];
            }
            $student->approval_status = 'approved';
            $student->save();
            $student->load('user');

            // Kirim email notifikasi approve ke siswa
            try {
                Mail::to($student->user->email)->send(new StudentApprovedMail($student));
            } catch (\Exception $mailEx) {
                // Gagal kirim email tidak menggagalkan proses approve
                Log::warning('Gagal kirim email approve ke ' . $student->user->email . ': ' . $mailEx->getMessage());
            }

            return ['success' => true, 'student' => $student];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal approve siswa: ' . $e->getMessage()];
        }
    }

    public function rejectStudent($id, $reason) {
        try {
            $student = Student::where('user_id', $id)->firstOrFail();
            if ($student->approval_status !== 'pending') {
                return ['success' => false, 'error' => 'Hanya siswa dengan status pending yang dapat ditolak'];
            }
            $student->approval_status    = 'rejected';
            $student->rejection_reason   = $reason;
            $student->save();
            $student->load('user');

            // Kirim email notifikasi reject ke siswa
            try {
                Mail::to($student->user->email)->send(new StudentRejectedMail($student, $reason));
            } catch (\Exception $mailEx) {
                Log::warning('Gagal kirim email reject ke ' . $student->user->email . ': ' . $mailEx->getMessage());
            }

            return ['success' => true, 'student' => $student];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal reject siswa: ' . $e->getMessage()];
        }
    }

    public function generateBarcodeText($studentId) {
        $student = Student::findOrFail($studentId);
        return $student->nis ?: ('STU-' . $student->id);
    }
}