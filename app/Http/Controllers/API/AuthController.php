<?php

namespace App\Http\Controllers\API;

use App\Services\AuthService;
use App\Constants\AppMessages;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// AuthController HTTP requests untuk authentication, Business logic dihandle di AuthService
class AuthController extends BaseController {
    protected $authService;
    public function __construct(AuthService $authService) {
        $this->authService = $authService;
        $this->middleware('auth:api')->except(['login', 'register', 'checkApproval']);
    }

    // Login dengan email dan password
    public function login(Request $request) {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ], [
            'email.required' => 'Email wajib diisi',
            'email.email' => AppMessages::ERROR_EMAIL_INVALID,
            'password.required' => 'Password wajib diisi',
        ]);
        $result = $this->authService->authenticateUser(
            $data['email'],
            $data['password'],
            $request->ip(),
            $request->userAgent()
        );
        if ($result === null) {
            return $this->responseUnauthorized('Email atau password salah');
        }
        if (isset($result['error'])) {
            return $this->responseForbidden($result['error']);
        }
        return $this->responseSuccess($result, 'Login berhasil');
    }

    // Register siswa baru
    public function register(Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8|confirmed',
            'nis' => ['required', 'string', Rule::unique('students', 'nis')],
            'sekolah' => 'required|string|max:255',
            'alamat' => 'required|string|max:500',
            'no_hp' => 'required|string|max:15',
        ], [
            'name.required' => 'Nama wajib diisi',
            'name.max' => 'Nama maksimal 255 karakter',
            'email.required' => 'Email wajib diisi',
            'email.email' => AppMessages::ERROR_EMAIL_INVALID,
            'email.unique' => 'Email sudah terdaftar',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
            'nis.required' => 'NIS wajib diisi',
            'nis.unique' => 'NIS sudah terdaftar',
            'sekolah.required' => 'Nama sekolah wajib diisi',
            'alamat.required' => 'Alamat wajib diisi',
            'no_hp.required' => 'Nomor HP wajib diisi',
        ]);
        $result = $this->authService->registerStudent($data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseCreated($result, 'Registrasi berhasil');
    }

    //Logout dan remove token
    public function logout(Request $request) {
        $user = $request->user();
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();
        $this->authService->logoutUser($user, $ipAddress, $userAgent);
        return $this->responseSuccess(null, 'Logout berhasil');
    }

    //Get data user yang sedang login
    public function me(Request $request) {
        $user = $request->user();
        $user->loadMissing(['driver']);
        $user->load(['student.buses.routes', 'student.halte']);

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

        // Format data user dengan bus/halte untuk siswa (sama seperti login)
        $userData = $user->toArray();
        if ($user->role === 'siswa' && $user->student) {
            $student = $user->student;
            $studentBus = $student->buses()->where('status', 'aktif')
                ->with(['routes'])->first();
            $halte  = $student->halte;
            $route  = $studentBus?->routes->first();
            $userData['student'] = [
                'id'              => $student->id,
                'user_id'         => $student->user_id,
                'nis'             => $student->nis,
                'sekolah'         => $student->sekolah,
                'kelas'           => $student->kelas,
                'alamat'          => $student->alamat,
                'no_hp'           => $student->no_hp,
                'approval_status' => $student->approval_status,
                'bus' => $studentBus ? [
                    'id'        => $studentBus->id,
                    'kode_bus'  => $studentBus->kode_bus,
                    'plat_nomor'=> $studentBus->plat_nomor,
                    'routes'    => $route ? [['id' => $route->id, 'nama_rute' => $route->nama_rute]] : [],
                ] : null,
                'halte' => $halte ? [
                    'id'         => $halte->id,
                    'nama_halte' => $halte->nama_halte,
                    'latitude'   => (float) $halte->latitude,
                    'longitude'  => (float) $halte->longitude,
                ] : null,
            ];
        }

        return $this->responseSuccess([
            'user' => $userData,
            'bus'  => $busData,
        ], 'Data user berhasil diambil');
    }

    //update password user
    public function changePassword(Request $request) {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Password saat ini wajib diisi',
            'new_password.required' => 'Password baru wajib diisi',
            'new_password.min' => 'Password baru minimal 8 karakter',
            'new_password.confirmed' => 'Konfirmasi password tidak cocok',
        ]);
        $result = $this->authService->updateUserPassword(
            $user,
            $data['current_password'],
            $data['new_password']
        );
        if (!$result['success']) {
            return $this->responseUnauthorized($result['error']);
        }
        return $this->responseSuccess(null, 'Password berhasil diubah');
    }

    //Update profil user (name, email)
    public function updateProfile(Request $request) {
        $user = $request->user();
        // PERBAIKAN: tambah no_hp dan alamat agar bisa tersimpan ke DB.
        // Sebelumnya hanya name & email yang diterima → no_hp dan alamat
        // hanya terupdate di memori Flutter tapi hilang saat login ulang.
        $data = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'email'  => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'no_hp'  => 'sometimes|nullable|string|max:20',
            'alamat' => 'sometimes|nullable|string|max:500',
        ], [
            'name.max'      => 'Nama maksimal 255 karakter',
            'email.email'   => AppMessages::ERROR_EMAIL_INVALID,
            'email.unique'  => 'Email sudah terdaftar',
            'no_hp.max'     => 'No HP maksimal 20 karakter',
            'alamat.max'    => 'Alamat maksimal 500 karakter',
        ]);
        if (empty($data)) {
            return $this->responseSuccess($user, 'Tidak ada data yang diubah');
        }
        $result = $this->authService->updateUserProfile($user, $data);
        return $this->responseUpdated($result['user'], 'Profil berhasil diperbarui');
    }

    public function uploadPhoto(Request $request){
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'photo.required' => 'Foto Wajib diupload',
            'photo.image' => 'File harus berupa gambar',
            'photo.mimes' => 'format foto harus jpeg, jpg, atau png',
            'photo.max' => 'Ukuran foto maksimal 2MB',
        ]);

        $user = $request->user();

        if ($user->photo) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
        }

        $path = $request->file('photo')->store('profile_photos', 'public');
        $user->photo = $path;
        $user->save();

        return $this->responseSuccess([
            'photo_url' => $user->photo_url,
        ], 'Foto profil berhasil diperbarui');
    }

    /**
     * Cek status approval siswa berdasarkan email.
     * Endpoint publik (tanpa token) — dipakai oleh pending_screen Flutter
     * untuk polling realtime setiap 5 detik.
     *
     * POST /api/auth/check-approval
     * Body: { "email": "siswa@example.com" }
     *
     * Response:
     *   { status: "pending" | "approved" | "rejected", rejection_reason?: string }
     */
    public function checkApproval(Request $request) {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = \App\Models\User::where('email', $request->email)
            ->where('role', 'siswa')
            ->with('student')
            ->first();

        if (!$user || !$user->student) {
            return $this->responseError('Akun tidak ditemukan', null, 404);
        }

        $status = $user->student->approval_status;

        return $this->responseSuccess([
            'status'           => $status,
            'rejection_reason' => $status === 'rejected' ? $user->student->rejection_reason : null,
        ], 'Status approval berhasil diambil');
    }
}