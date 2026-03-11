<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DriverService {

    public function getAllDrivers($perPage = 15) {
        return Driver::with('user')->paginate($perPage);
    }

    public function getDriverById($id) {
        return Driver::with('user')->findOrFail($id);
    }

    public function createDriver($data) {
        try {
            return DB::transaction(function () use ($data) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => 'driver',
                    'api_token' => Str::random(60),
                ]);
                $driver = Driver::create([
                    'user_id' => $user->id,
                    'nik' => $data['nik'],
                    'no_hp' => $data['no_hp'],
                    'alamat' => $data['alamat'],
                ]);
                return [
                    'success' => true,
                    'user' => $user,
                    'driver' => $driver,
                ];
            });
        } catch (\Exception $e) {
            $message = 'Gagal membuat driver';
            if (strpos($e->getMessage(), 'Integrity constraint violation') !== false) {
                if (strpos($e->getMessage(), 'nik_unique') !== false) {
                    $message = 'NIK sudah terdaftar';
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

    public function updateDriver($id, $data) {
        try {
            // Cari by user_id karena Flutter kirim users.id
            $driver = Driver::where('user_id', $id)->firstOrFail();
            $user = $driver->user;
            $userFields = [];
            $driverFields = [];
            foreach ($data as $key => $value) {
                if (in_array($key, ['name', 'email', 'password'])) {
                    $userFields[$key] = $value;
                } else {
                    $driverFields[$key] = $value;
                }
            }
            if (!empty($userFields)) {
                if (isset($userFields['password'])) {
                    $userFields['password'] = Hash::make($userFields['password']);
                }
                $user->update($userFields);
            }
            if (!empty($driverFields)) {
                $driver->update($driverFields);
            }
            return [
                'success' => true,
                'user' => $user->fresh(),
                'driver' => $driver->fresh(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal update driver: ' . $e->getMessage(),
            ];
        }
    }

    public function deleteDriver($id) {
        try {
            // Cari by user_id karena Flutter kirim users.id
            $driver = Driver::where('user_id', $id)->firstOrFail();
            $userId = $driver->user_id;
            $driver->delete();
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
                'error' => 'Gagal menghapus driver: ' . $e->getMessage(),
            ];
        }
    }

    public function getDriverBusHistory($driverId) {
        $driver = Driver::findOrFail($driverId);
        return $driver->buses()->with('routes')->paginate(15);
    }

    public function getActiveAssignments($driverId) {
        $driver = Driver::findOrFail($driverId);
        $today = now()->toDateString();
        return $driver->buses()->wherePivot('tanggal_mulai', '<=', $today)->where(function ($q) use ($today) {
            $q->whereNull('bus_driver.tanggal_selesai')->orWhere('bus_driver.tanggal_selesai', '>=', $today);
            })->get();
    }

    public function mapBusAssignmentsData($buses) {
        return $buses->map(function ($bus) {
            $routes = $bus->routes()->with('haltes')->get();
            $students = $bus->students()->with('user')->get();
            return [
                'id' => $bus->id,
                'kode_bus' => $bus->kode_bus,
                'plat_nomor' => $bus->plat_nomor,
                'status' => $bus->status,
                'assignment' => [
                    'tanggal_mulai' => $bus->pivot->tanggal_mulai,
                    'tanggal_selesai' => $bus->pivot->tanggal_selesai,
                    'gps_status' => $bus->pivot->gps_status,
                    'last_gps_update' => $bus->pivot->last_gps_update,
                ],
                'routes' => $routes->map(function($route) {
                    return [
                        'id' => $route->id,
                        'nama_rute' => $route->nama_rute,
                        'haltes' => $route->haltes->map(function($halte) {
                            return [
                                'id' => $halte->id,
                                'nama_halte' => $halte->nama_halte,
                                'latitude' => $halte->latitude,
                                'longitude' => $halte->longitude,
                            ];
                        })
                    ];
                }),
                'students' => $students->map(function($student) {
                    return [
                        'id' => $student->id,
                        'nis' => $student->nis,
                        'nama_siswa' => $student->user->name,
                        'no_hp' => $student->no_hp ?? 'N/A',
                        'halte_id' => $student->pivot->halte_id,
                    ];
                })
            ];
        })->all();
    }
}