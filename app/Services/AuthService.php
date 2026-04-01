<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Jobs\LogActivityAsync;
use App\Traits\CreatesUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AuthService {
    use CreatesUser;

    public function authenticateUser($email, $password, $ipAddress, $userAgent) {
        $user = User::where('email', $email)->first();
        if (!$user || !Hash::check($password, $user->password)) {
            LogActivityAsync::dispatch('login_failed', null, [
                'description' => 'Percobaan login gagal untuk email: ' . $email,
                'status'      => 'failed',
            ]);
            return null;
        }
        if ($user->is_suspended) {
            LogActivityAsync::dispatch('login_blocked', $user->id, [
                'description' => 'Percobaan login dari akun yang suspend',
                'status'      => 'blocked',
            ]);
            return ['error' => 'Akun Anda telah disuspend'];
        }
        if ($user->role === 'siswa') {
            $student = $user->student;
            if (!$student) {
                return ['error' => 'Profil siswa tidak ditemukan'];
            }
            if ($student->approval_status === 'rejected') {
                return ['error' => 'Akun Anda telah ditolak oleh admin'];
            }
            if ($student->approval_status !== 'approved') {
                return ['error' => 'Akun Anda masih menunggu persetujuan admin'];
            }
        }
        $user->api_token             = Str::random(60);
        $user->token_expires_at      = now()->addHours(24);
        $user->last_login_at         = now();
        $user->last_login_ip         = $ipAddress;
        $user->last_login_user_agent = $userAgent;
        $user->save();
        LogActivityAsync::dispatch('login', $user->id, [
            'description' => 'User berhasil login',
            'status'      => 'success',
        ]);
        return [
            'token'            => $user->api_token,
            'user'             => $user,
            'token_expires_at' => $user->token_expires_at,
        ];
    }

    public function registerStudent($data) {
        try {
            return DB::transaction(function () use ($data) {
                $user    = $this->createUser('siswa', $data);
                $student = Student::create([
                    'user_id' => $user->id,
                    'nis'     => $data['nis'],
                    'sekolah' => $data['sekolah'],
                    'kelas'   => 'Belum ditentukan',
                    'alamat'  => $data['alamat'],
                    'no_hp'   => $data['no_hp'],
                ]);
                return [
                    'success' => true,
                    'token'   => $user->api_token,
                    'user'    => $user,
                    'student' => $student,
                ];
            });
        } catch (Throwable $e) {
            $message = $this->parseDuplicateError($e, 'Registrasi gagal', [
                'nis_unique'   => 'NIS sudah terdaftar',
                'no_hp_unique' => 'Nomor HP sudah terdaftar',
                'email_unique' => 'Email sudah terdaftar',
            ]);
            return ['success' => false, 'error' => $message];
        }
    }

    public function logoutUser($user) {
        if (!$user) {
            return false;
        }
        LogActivityAsync::dispatch('logout', $user->id, [
            'description' => 'User logout',
            'status'      => 'success',
        ]);
        $user->api_token        = null;
        $user->token_expires_at = null;
        return $user->save();
    }

    public function updateUserPassword($user, $currentPassword, $newPassword) {
        if (!Hash::check($currentPassword, $user->password)) {
            LogActivityAsync::dispatch('password_change_failed', $user->id, [
                'description' => 'Percobaan ubah password gagal',
                'status'      => 'failed',
            ]);
            return ['success' => false, 'error' => 'Password saat ini tidak sesuai'];
        }
        $user->password = Hash::make($newPassword);
        $user->save();
        LogActivityAsync::dispatch('password_changed', $user->id, [
            'description' => 'Password user berhasil diubah',
            'status'      => 'success',
        ]);
        return ['success' => true];
    }

    public function updateUserProfile($user, $data) {
        $user->update($data);
        LogActivityAsync::dispatch('profile_updated', $user->id, [
            'description' => 'Profil user diupdate',
            'status'      => 'success',
        ]);
        return ['success' => true, 'user' => $user->fresh()];
    }
}
