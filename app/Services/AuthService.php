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
        $user->token_expires_at      = now()->addDays(2);
        $user->last_login_at         = now();
        $user->last_login_ip         = $ipAddress;
        $user->last_login_user_agent = $userAgent;
        $user->save();
        LogActivityAsync::dispatch('login', $user->id, [
            'description' => 'User berhasil login',
            'status'      => 'success',
        ]);
        // Untuk driver: sertakan data bus aktif langsung di response login
        // sehingga Flutter tidak perlu request tambahan GET /driver/buses
        $busData = null;
        if ($user->role === 'driver' && $user->driver) {
            $today = now()->toDateString();
            $bus = $user->driver->buses()
                ->wherePivot('tanggal_mulai', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('bus_driver.tanggal_selesai')
                      ->orWhere('bus_driver.tanggal_selesai', '>=', $today);
                })
                ->with(['routes.haltes', 'routes.polylines'])
                ->first();

            if ($bus) {
                $busData = [
                    'id'         => $bus->id,
                    'kode_bus'   => $bus->kode_bus,
                    'plat_nomor' => $bus->plat_nomor,
                    'status'     => $bus->status,
                    'assignment' => [
                        'tanggal_mulai'   => $bus->pivot->tanggal_mulai,
                        'tanggal_selesai' => $bus->pivot->tanggal_selesai,
                        'gps_status'      => $bus->pivot->gps_status,
                        'last_gps_update' => $bus->pivot->last_gps_update,
                    ],
                    'routes' => $bus->routes->map(fn($r) => [
                        'id'        => $r->id,
                        'bus_id'    => $bus->id,
                        'nama_rute' => $r->nama_rute,
                        'haltes'    => $r->haltes->map(fn($h) => [
                            'id'         => $h->id,
                            'nama_halte' => $h->nama_halte,
                            'latitude'   => (float) $h->latitude,
                            'longitude'  => (float) $h->longitude,
                            'urutan'     => $h->pivot->urutan,
                        ])->sortBy('urutan')->values(),
                        'polyline'  => $r->polylines->map(fn($p) => [
                            'latitude'  => (float) $p->latitude,
                            'longitude' => (float) $p->longitude,
                            'urutan'    => $p->urutan,
                        ])->values(),
                    ])->values(),
                ];
            }
        }

        // Load relasi driver/student agar Flutter tidak perlu /auth/me tambahan
        $user->loadMissing(['driver']);
        $user->load(['student.buses.routes', 'student.halte']);

        // Format data user dengan bus/halte untuk siswa
        $userData = $user->toArray();
        if ($user->role === 'siswa' && $user->student) {
            $student = $user->student;
            // Ambil bus aktif pertama
            $studentBus = $student->buses()->where('status', 'aktif')
                ->with(['routes'])
                ->first();
            $halte = $student->halte;
            $route = $studentBus?->routes->first();

            // Override field 'student' di userData dengan info lengkap
            $userData['student'] = [
                'id'              => $student->id,
                'user_id'         => $student->user_id,
                'nis'             => $student->nis,
                'sekolah'         => $student->sekolah,
                'kelas'           => $student->kelas,
                'alamat'          => $student->alamat,
                'no_hp'           => $student->no_hp,
                'approval_status' => $student->approval_status,
                // Info bus yang di-assign ke siswa ini
                'bus' => $studentBus ? [
                    'id'        => $studentBus->id,
                    'kode_bus'  => $studentBus->kode_bus,
                    'plat_nomor'=> $studentBus->plat_nomor,
                    'routes'    => $route ? [['id' => $route->id, 'nama_rute' => $route->nama_rute]] : [],
                ] : null,
                // Halte penjemputan siswa
                'halte' => $halte ? [
                    'id'         => $halte->id,
                    'nama_halte' => $halte->nama_halte,
                    'latitude'   => (float) $halte->latitude,
                    'longitude'  => (float) $halte->longitude,
                ] : null,
            ];
        }

        return [
            'token'            => $user->api_token,
            'user'             => $userData,
            'token_expires_at' => $user->token_expires_at,
            'bus'              => $busData, // null jika bukan driver / belum dapat bus
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