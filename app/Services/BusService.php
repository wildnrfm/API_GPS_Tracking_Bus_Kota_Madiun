<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\BusDriver;

class BusService {
    public function getAllBuses($perPage = 15) {
        // Include active driver dalam response
        return Bus::with(['routes', 'drivers' => function($q) {
            $today = now()->toDateString();
            $q->wherePivot('tanggal_mulai', '<=', $today)
              ->where(function ($q2) use ($today) {
                  $q2->whereNull('bus_driver.tanggal_selesai')
                     ->orWhere('bus_driver.tanggal_selesai', '>=', $today);
              })->with('user');
        }])->paginate($perPage);
    }

    public function getBusById($id) {
        return Bus::with(['routes', 'drivers' => function($q) {
            $today = now()->toDateString();
            $q->wherePivot('tanggal_mulai', '<=', $today)
              ->where(function ($q2) use ($today) {
                  $q2->whereNull('bus_driver.tanggal_selesai')
                     ->orWhere('bus_driver.tanggal_selesai', '>=', $today);
              })->with('user');
        }])->findOrFail($id);
    }

    public function createBus($data) {
        try {
            $bus = Bus::create(
                collect($data)->except('nama_rute')->toArray()
            );
            if (isset($data['nama_rute']) && $data['nama_rute'] !== '') {
                $bus->routes()->create([
                    'nama_rute' => $data['nama_rute'],
                ]);
            }
            $bus->load('routes');
            return [
                'success' => true,
                'bus' => $bus,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal membuat bus: ' . $e->getMessage(),
            ];
        }
    }

    public function updateBus($id, $data) {
        try {
            $bus = Bus::findOrFail($id);
            if (isset($data['nama_rute'])) {
                $routeName = $data['nama_rute'];
                unset($data['nama_rute']);
            } else {
                $routeName = null;
            }
            $bus->update($data);
            if ($routeName !== null) {
                $existing = $bus->routes()->first();
                if ($routeName === '' || $routeName === null) {
                    if ($existing) {
                        $existing->delete();
                    }
                } else {
                    if ($existing) {
                        $existing->update(['nama_rute' => $routeName]);
                    } else {
                        $bus->routes()->create(['nama_rute' => $routeName]);
                    }
                }
            }
            $bus->load('routes');
            return [
                'success' => true,
                'bus' => $bus->fresh(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal update bus: ' . $e->getMessage(),
            ];
        }
    }

    public function deleteBus($id) {
        try {
            $bus = Bus::findOrFail($id);
            $bus->delete();
            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Gagal menghapus bus: ' . $e->getMessage(),
            ];
        }
    }

    public function getBusStudents($busId) {
        $bus = Bus::findOrFail($busId);
        return $bus->students()->with('user')->paginate(15);
    }

    public function getBusDrivers($busId) {
        $bus = Bus::findOrFail($busId);
        return $bus->drivers()->with('user')->paginate(15);
    }

    public function getActiveDriver($busId) {
        $today = now()->toDateString();
        return Bus::findOrFail($busId)->drivers()
            ->wherePivot('tanggal_mulai', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('bus_driver.tanggal_selesai')
                  ->orWhere('bus_driver.tanggal_selesai', '>=', $today);
            })->with('user')->first();
    }

    public function assignDriverToBus($busId, $driverId, $tanggalMulai, $tanggalSelesai = null) {
        try {
            Bus::findOrFail($busId);

            // Auto-end semua assignment aktif driver ini di bus LAIN
            BusDriver::where('driver_id', $driverId)
                ->where('bus_id', '!=', $busId)
                ->whereNull('tanggal_selesai')
                ->update(['tanggal_selesai' => now()->toDateString()]);

            // Auto-end assignment driver LAIN yang aktif di bus ini
            BusDriver::where('bus_id', $busId)
                ->where('driver_id', '!=', $driverId)
                ->whereNull('tanggal_selesai')
                ->update(['tanggal_selesai' => now()->toDateString()]);

            // Jika driver ini sudah punya assignment aktif di bus yang sama — reuse, tidak perlu buat baru
            $existing = BusDriver::where('driver_id', $driverId)
                ->where('bus_id', $busId)
                ->whereNull('tanggal_selesai')
                ->first();

            if ($existing) {
                return ['success' => true, 'assignment' => $existing->load('driver.user')];
            }

            // Buat assignment baru
            $busDriver = BusDriver::create([
                'bus_id'          => $busId,
                'driver_id'       => $driverId,
                'tanggal_mulai'   => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
            ]);

            return ['success' => true, 'assignment' => $busDriver->load('driver.user')];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Gagal assign driver: ' . $e->getMessage()];
        }
    }
}