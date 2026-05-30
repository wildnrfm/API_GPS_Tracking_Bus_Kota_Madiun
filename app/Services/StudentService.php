<?php

namespace App\Services;

use App\Mail\StudentApprovedMail;
use App\Mail\StudentRejectedMail;
use App\Models\Student;
use App\Models\StudentRejectionHistory;
use App\Models\User;
use App\Traits\CreatesUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StudentService {
    use CreatesUser;

    public function getAllStudents($perPage = 15, $approvalStatus = null) {
        $query = Student::with(['user', 'buses.routes', 'halte']);
        if ($approvalStatus) {
            // When requesting rejected students, include students that
            // currently have approval_status='rejected' OR those that
            // have past rejection histories (so re-applied users still
            // appear in the "rejected" list as historical entries).
            if ($approvalStatus === 'rejected') {
                $query->where(function ($q) {
                    $q->where('approval_status', 'rejected')
                      ->orWhereHas('rejectionHistories');
                });
            } else {
                $query->where('approval_status', $approvalStatus);
            }
        }

        return $query->paginate($perPage);
    }

    public function getStudentById($id) {
        return Student::with(['user', 'buses.routes', 'halte', 'rejectionHistories.rejectedBy'])->findOrFail($id);
    }

    public function getStudentRejectionHistories($studentId, $perPage = 50) {
        return StudentRejectionHistory::where('student_id', $studentId)
            ->with('rejectedBy')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getAllRejectionHistories($perPage = 50) {
        return StudentRejectionHistory::with('rejectedBy')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function deleteRejectionHistory($id) {
        try {
            $history = StudentRejectionHistory::findOrFail($id);
            $history->delete();
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal menghapus riwayat penolakan: ' . $e->getMessage()];
        }
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
            $student = Student::findOrFail($id);
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
            $student = Student::findOrFail($id);
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
            $student = Student::findOrFail($id);
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

    public function rejectStudent($id, $reason, $rejectedBy = null) {
        try {
            $student = Student::find($id);
            if (!$student) {
                return ['success' => false, 'error' => 'Siswa tidak ditemukan', 'code' => 404];
            }
            if ($student->approval_status !== 'pending') {
                return ['success' => false, 'error' => 'Hanya siswa dengan status pending yang dapat ditolak'];
            }
            // Prepare snapshot from student + user
            $student->load('user');
            $user = $student->user;

            $snapshot = [
                'student_id'  => $student->id,
                'user_id'     => $user?->id,
                'name'        => $user?->name ?? null,
                'email'       => $user?->email ?? null,
                'nis'         => $student->nis ?? null,
                'sekolah'     => $student->sekolah ?? null,
                'kelas'       => $student->kelas ?? null,
                'alamat'      => $student->alamat ?? null,
                'no_hp'       => $student->no_hp ?? null,
                'rejected_by' => $rejectedBy,
                'reason'      => $reason,
            ];

            // Create immutable rejection history snapshot
            StudentRejectionHistory::create($snapshot);

            // Send notification to student's email if available BEFORE deleting user
            try {
                if ($user?->email) {
                    Mail::to($user->email)->send(new StudentRejectedMail($student, $reason));
                }
            } catch (\Exception $mailEx) {
                // Log but do not abort: deletion should proceed regardless
                Log::warning('Gagal kirim email reject ke ' . ($user?->email ?? 'unknown') . ': ' . $mailEx->getMessage());
            }

            // Delete student record and associated user after snapshot and notification attempt
            $deleteResult = $this->deleteStudent($id);
            if (!$deleteResult['success']) {
                return ['success' => false, 'error' => 'Gagal menghapus siswa setelah reject: ' . ($deleteResult['error'] ?? '')];
            }

            // Return snapshot both as 'history' and 'student' so controller/frontend
            // that expect a 'student' key keep working (frontend reads response.data.student).
            return ['success' => true, 'history' => $snapshot, 'student' => $snapshot];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal reject siswa: ' . $e->getMessage()];
        }
    }

    public function generateBarcodeText($studentId) {
        $student = Student::findOrFail($studentId);
        return $student->nis ?: ('STU-' . $student->id);
    }
}