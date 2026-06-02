<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait CreatesUser {
    /**
     * Buat record User baru dengan role tertentu.
     * Dipakai oleh AuthService, StudentService, DriverService, UserService.
     */
    protected function createUser(string $role, array $data): User {
        $fields = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role'      => $role,
            'api_token' => Str::random(60),
        ];
        if (isset($data['photo'])) {
            $fields['photo'] = $data['photo'];
        }
        return User::create($fields);
    }

    /**
     * Parse pesan error duplicate dari exception Integrity constraint violation.
     * Dipakai oleh AuthService, StudentService, DriverService.
     */
    protected function parseDuplicateError(\Throwable $e, string $default, array $fieldMap = []): string {
        $msg = $e->getMessage();
        if (strpos($msg, 'Integrity constraint violation') !== false) {
            foreach ($fieldMap as $key => $message) {
                if (strpos($msg, $key) !== false) {
                    return $message;
                }
            }
        }
        return $default;
    }

    /**
     * Update field user (name, email, password, photo) dan field profile (siswa/driver).
     * Dipakai oleh StudentService dan DriverService.
     */
    protected function updateUserAndProfile($user, $profile, array $data): void {
        $userFields    = [];
        $profileFields = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'email', 'password', 'photo'])) {
                $userFields[$key] = $value;
            } else {
                $profileFields[$key] = $value;
            }
        }

        if (!empty($userFields)) {
            if (isset($userFields['password'])) {
                $userFields['password'] = Hash::make($userFields['password']);
            }
            $user->update($userFields);
        }

        if (!empty($profileFields)) {
            $profile->update($profileFields);
        }
    }
}
