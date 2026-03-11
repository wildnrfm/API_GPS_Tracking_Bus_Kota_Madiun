<?php

namespace App\Services;

use App\Models\User;
use App\Models\Student;
use App\Models\Driver;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService {
    public function getAllUsers($perPage = 15) {
        return User::paginate($perPage);
    }

    public function getUserById($id) {
        return User::find($id);
    }

    public function deleteUserWithCascade($id) {
        try {
            $user = User::findOrFail($id);
            if ($user->role === 'driver') {
                $driver = Driver::where('user_id', $id)->first();
                if ($driver) {
                    $driver->delete();
                }
            } elseif ($user->role === 'siswa') {
                $student = Student::where('user_id', $id)->first();
                if ($student) {
                    $student->delete();
                }
            }
            $user->delete();
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal menghapus user: ' . $e->getMessage(),
            ];
        }
    }

    public function createAdmin($data) {
        try {
            $admin = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'admin',
                'api_token' => Str::random(60),
            ]);
            return [
                'success' => true,
                'user' => $admin,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal membuat admin: ' . $e->getMessage(),
            ];
        }
    }

    public function updateAdmin($id, $data) {
        try {
            $admin = User::where('role', 'admin')->findOrFail($id);
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }
            $admin->update($data);
            return [
                'success' => true,
                'user' => $admin->fresh(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal update admin: ' . $e->getMessage(),
            ];
        }
    }

    public function deleteAdmin($id) {
        try {
            $admin = User::where('role', 'admin')->findOrFail($id);
            $admin->delete();
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal menghapus admin: ' . $e->getMessage(),
            ];
        }
    }
}
