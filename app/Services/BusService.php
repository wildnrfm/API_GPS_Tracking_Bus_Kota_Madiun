<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\BusDriver;
use Carbon\Carbon;

class BusService {
    public function getAllBuses() {
        $today = now()->toDateString();

        $buses = Bus::with(['routes', 'drivers' => function($q) use ($today) {
            $q->wherePivot('tanggal_mulai', '<=', $today)
              ->where(function ($q2) use ($today) {
                  $q2->whereNull('bus_driver.tanggal_selesai')
                     ->orWhere('bus_driver.tanggal_selesai', '>=', $today);
              })->with('user');
        }])->get();

        // Map manual agar gps_status & current_position ikut ter-serialize
        return $buses->map(function ($bus) {
            $assignment = $bus->drivers->first()?->pivot ?? null;

            // Auto-reset GPS:
            // - NULL last_gps_update = sesi lama yang tidak pernah kirim koordinat
            // - last_gps_update > 5 menit lalu = driver crash/force-close
            $rawGpsStatus = $assignment?->gps_status ?? 'off';
            $lastUpdate   = $assignment?->last_gps_update;
            $gpsStatus    = $rawGpsStatus;
            if ($rawGpsStatus === 'on') {
                if (!$lastUpdate) {
                    // NULL → langsung reset
                    $gpsStatus = 'off';
                    $assignment?->update(['gps_status' => 'off']);
                } elseif (now()->diffInMinutes(Carbon::parse($lastUpdate)) > 5) {
                    $gpsStatus = 'off';
                    $assignment?->update(['gps_status' => 'off']);
                }
            }

            $latest = \App\Models\GpsTrack::where('bus_id', $bus->id)
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0)
                ->orderBy('recorded_at', 'desc')
                ->first(['latitude', 'longitude', 'speed', 'recorded_at']);

            return [
                'id'              => $bus->id,
                'kode_bus'        => $bus->kode_bus,
                'plat_nomor'      => $bus->plat_nomor,
                'status'          => $bus->status,
                'gps_status'      => $gpsStatus,
                'last_gps_update' => $assignment?->last_gps_update,
                // current_position hanya dikirim jika GPS sedang aktif
                'current_position' => ($gpsStatus === 'on' && $latest) ? [
                    'latitude'    => (float) $latest->latitude,
                    'longitude'   => (float) $latest->longitude,
                    'speed'       => (float) $latest->speed,
                    'recorded_at' => (string) $latest->recorded_at,
                ] : null,
                'routes'  => $bus->routes->map(fn($r) => [
                    'id'        => $r->id,
                    'nama_rute' => $r->nama_rute,
                ])->values(),
                'drivers' => $bus->drivers->map(fn($d) => [
                    'id'      => $d->id,
                    'user_id' => $d->user_id,
                    'user'    => ['id' => $d->user?->id, 'name' => $d->user?->name],
                    'pivot'   => [
                        'tanggal_selesai' => $d->pivot->tanggal_selesai,
                        'gps_status'      => $d->pivot->gps_status,
                    ],
                ])->values(),
                'created_at' => (string) $bus->created_at,
            ];
        })->values();
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