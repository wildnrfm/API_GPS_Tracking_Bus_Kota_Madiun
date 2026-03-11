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
        $this->middleware('auth:api')->except(['login', 'register']);
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
        $this->authService->logoutUser($user);
        return $this->responseSuccess(null, 'Logout berhasil');
    }

    //Get data user yang sedang login
    public function me(Request $request) {
        return $this->responseSuccess(
            $request->user(),
            'Data user berhasil diambil'
        );
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
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ], [
            'name.max' => 'Nama maksimal 255 karakter',
            'email.email' => AppMessages::ERROR_EMAIL_INVALID,
            'email.unique' => 'Email sudah terdaftar',
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
}

