<?php

namespace App\Services;

use App\Models\User;
use App\Models\Student;
use App\Models\Driver;
use App\Traits\CreatesUser;
use Illuminate\Support\Facades\Hash;

class UserService {
    use CreatesUser;

    public function getAllUsers($perPage = 15) {
        return User::paginate($perPage);
    }

    public function getUserById($id) {
        return User::find($id);
    }

    public function deleteUserWithCascade($id) {
        try {
            $user = User::findOrFail($id);
            // DB cascade (cascadeOnDelete pada FK) akan hapus student/driver otomatis,
            // tapi kita tetap hapus manual agar Observer berjalan dengan benar
            if ($user->role === 'driver') {
                $driver = Driver::where('user_id', $id)->first();
                if ($driver) $driver->delete();
            } elseif ($user->role === 'siswa') {
                $student = Student::where('user_id', $id)->first();
                if ($student) $student->delete();
            }
            $user->delete();
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal menghapus user: ' . $e->getMessage()];
        }
    }

    public function createAdmin($data) {
        try {
            $admin = $this->createUser('admin', $data);
            return ['success' => true, 'user' => $admin];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal membuat admin: ' . $e->getMessage()];
        }
    }

    public function updateAdmin($id, $data) {
        try {
            $admin = User::where('role', 'admin')->findOrFail($id);
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }
            $admin->update($data);
            return ['success' => true, 'user' => $admin->fresh()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal update admin: ' . $e->getMessage()];
        }
    }

    public function deleteAdmin($id) {
        try {
            $admin = User::where('role', 'admin')->findOrFail($id);
            $admin->delete();
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal menghapus admin: ' . $e->getMessage()];
        }
    }
}
