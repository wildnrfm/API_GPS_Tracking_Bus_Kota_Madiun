<?php

namespace App\Http\Controllers\API;

use App\Models\GpsTrack;
use App\Models\Bus;
use App\Models\BusDriver;
use App\Services\OfflineDataService;
use App\Constants\AppMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GpsTrackController extends BaseController {
    public function __construct() {
        $this->middleware('auth:api');
    }

    //Simpan lokasi GPS dari driver (untuk mobile)
    public function storeByDriver(Request $request) {
        $user   = $request->user();
        $driver = $user->driver;
        if (!$driver) {
            return $this->responseNotFound('Profil driver tidak ditemukan');
        }

        // Get active bus assignment
        $activeAssignment = BusDriver::select('id', 'bus_id', 'driver_id')->where('driver_id', $driver->id)->where(function($q) {
            $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', now()->toDateString());
            })->orderBy('created_at', 'desc')->first();
        if (!$activeAssignment) {
            return $this->responseNotFound('Tidak ada penugasan bus aktif untuk driver ini');
        }
        $busId = $activeAssignment->bus_id;
        $bus = Bus::findOrFail($busId);
        if (!$bus || $bus->status !== 'aktif') {
            return $this->responseForbidden('Bus tidak operasional. Status: ' . ($bus->status ?? 'tidak aktif'));
        }
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
        ], [
            'latitude.required' => 'Latitude harus diisi',
            'latitude.between' => 'Latitude harus antara -90 dan 90',
            'longitude.required' => 'Longitude harus diisi',
            'longitude.between' => 'Longitude harus antara -180 dan 180',
            'speed.numeric' => 'Kecepatan harus berupa angka',
        ]);
        $gpsTrack = GpsTrack::create([
            'bus_id' => $busId,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'speed' => $data['speed'] ?? 0,
            'recorded_at' => now(),
        ]);
        OfflineDataService::logDataSync(
            'gps_track',
            $gpsTrack->id,
            $request->input('device_id', 'unknown'),
            $gpsTrack->toArray(),
            'synced'
        );
        return $this->responseCreated([
            'id' => $gpsTrack->id,
            'bus_id' => $gpsTrack->bus_id,
            'latitude' => $gpsTrack->latitude,
            'longitude' => $gpsTrack->longitude,
            'speed' => $gpsTrack->speed,
            'recorded_at' => $gpsTrack->recorded_at,
        ], 'Lokasi GPS berhasil dicatat');
    }

    // lokasi GPS terbaru dari semua bus
    public function latest(Request $request) {
        $limit = $request->input('limit', 20);
        $latestGps = GpsTrack::select('id', 'bus_id', 'latitude', 'longitude', 'speed', 'recorded_at')->orderBy('recorded_at', 'desc')->limit($limit)->get();
        $groupedByBus = [];
        foreach ($latestGps as $track) {
            if (!isset($groupedByBus[$track->bus_id])) {
                $groupedByBus[$track->bus_id] = $track;
            }
        }
        return $this->responseSuccess([
            'data' => array_values($groupedByBus),
            'count' => count($groupedByBus),
            'recorded_at' => now()
        ], AppMessages::SUCCESS_RETRIEVED);
    }

    //Dashboard admin - lihat semua bus dengan status GPS terbaru
    public function dashboard(Request $request) {
        $buses = Bus::select('id', 'kode_bus', 'plat_nomor')->get();
        $dashboardData = [];
        foreach ($buses as $bus) {
            // [FIX] Filter lat/lng = 0 agar current_position null bila belum ada GPS nyata
            $latestGps = GpsTrack::select('id', 'bus_id', 'latitude', 'longitude', 'speed', 'recorded_at')
                ->where('bus_id', $bus->id)
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0)
                ->orderBy('recorded_at', 'desc')
                ->first();
            $activeDriver = BusDriver::select('id', 'bus_id', 'driver_id', 'gps_status', 'last_gps_update')->where('bus_id', $bus->id)->where(function($q) {
                    $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', now()->toDateString());
                })->with('driver:id,user_id,no_hp', 'driver.user:id,name')->first();
            $gpsStatus = $activeDriver ? $activeDriver->gps_status : 'off';
            $lastGpsUpdate = $activeDriver ? $activeDriver->last_gps_update : null;
            $dashboardData[] = [
                'bus_id' => $bus->id,
                'bus_code' => $bus->kode_bus,
                'bus_plate' => $bus->plat_nomor,
                'gps_status' => $gpsStatus,
                'last_gps_update' => $lastGpsUpdate,
                'current_position' => $latestGps ? [
                    'latitude' => $latestGps->latitude,
                    'longitude' => $latestGps->longitude,
                    'speed' => $latestGps->speed,
                    'recorded_at' => $latestGps->recorded_at,
                ] : null,
                'driver' => $activeDriver && $activeDriver->driver && $activeDriver->driver->user ? [
                    'id' => $activeDriver->driver->id,
                    'name' => $activeDriver->driver->user->name,
                    'phone' => $activeDriver->driver->no_hp,
                ] : null,
            ];
        }
        return $this->responseSuccess([
            'count' => count($dashboardData),
            'data' => $dashboardData,
            'timestamp' => now()
        ], AppMessages::SUCCESS_RETRIEVED);
    }

    // getlokasi GPS terbaru bus tertentu
    public function latestByBus(Request $request, $busId) {
        $bus = Bus::select('id', 'kode_bus', 'plat_nomor')->findOrFail($busId);
        $gpsTrack = GpsTrack::select('id', 'bus_id', 'latitude', 'longitude', 'speed', 'recorded_at')->where('bus_id', $busId)->orderBy('recorded_at', 'desc')->first();
        if (!$gpsTrack) {
            return $this->responseNotFound('Tidak ada data GPS untuk bus ini');
        }
        return $this->responseSuccess([
            'id' => $gpsTrack->id,
            'bus_id' => $gpsTrack->bus_id,
            'bus_code' => $bus->kode_bus,
            'bus_plate' => $bus->plat_nomor,
            'latitude' => $gpsTrack->latitude,
            'longitude' => $gpsTrack->longitude,
            'speed' => $gpsTrack->speed,
            'recorded_at' => $gpsTrack->recorded_at
        ]);
    }

    // Riwayat GPS bus tertentu
    public function history(Request $request, $busId) {
        Bus::findOrFail($busId);
        $query = GpsTrack::where('bus_id', $busId);
        if ($request->has('date')) {
            $date = $request->input('date');
            $query->whereDate('recorded_at', $date);
        }
        $gpsHistory = $query->orderBy('recorded_at', 'desc')->paginate($request->input('per_page', 50));
        return $this->responsePaginated($gpsHistory, AppMessages::SUCCESS_RETRIEVED);
    }

    //get GPS dengan filter
    public function index(Request $request) {
        $query = GpsTrack::with('bus');
        if ($request->has('bus_id')) {
            $query->where('bus_id', $request->input('bus_id'));
        }
        if ($request->has('date')) {
            $date = $request->input('date');
            $query->whereDate('recorded_at', $date);
        }
        $gpsData = $query->orderBy('recorded_at', 'desc')->paginate($request->input('per_page', 50));
        return $this->responsePaginated($gpsData, AppMessages::SUCCESS_RETRIEVED);
    }

    /**
     * SSE — stream posisi GPS semua bus secara real-time ke admin.
     * Admin subscribe sekali, server kirim update setiap 3 detik.
     * Tidak butuh WebSocket / Pusher / Redis.
     */
    public function stream(Request $request) {
        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            abort(403, 'Forbidden');
        }

        return response()->stream(function () {
            $maxIterations = 200; // ~10 menit maksimum
            $i = 0;
            while ($i < $maxIterations) {
                if (connection_aborted()) break;

                $buses = Bus::select('id', 'kode_bus', 'plat_nomor')->get();
                $payload = [];
                foreach ($buses as $bus) {
                    $gps = GpsTrack::select('latitude', 'longitude', 'speed', 'recorded_at')
                        ->where('bus_id', $bus->id)
                        ->where('latitude', '!=', 0)
                        ->where('longitude', '!=', 0)
                        ->orderBy('recorded_at', 'desc')
                        ->first();

                    $activeDriver = BusDriver::select('gps_status', 'last_gps_update', 'driver_id')
                        ->where('bus_id', $bus->id)
                        ->where(function ($q) {
                            $q->whereNull('tanggal_selesai')
                              ->orWhere('tanggal_selesai', '>=', now()->toDateString());
                        })
                        ->with('driver:id,user_id', 'driver.user:id,name')
                        ->first();

                    $payload[] = [
                        'bus_id'      => $bus->id,
                        'bus_code'    => $bus->kode_bus,
                        'bus_plate'   => $bus->plat_nomor,
                        'gps_status'  => $activeDriver?->gps_status ?? 'off',
                        'driver_name' => $activeDriver?->driver?->user?->name ?? '',
                        'position'    => $gps ? [
                            'latitude'    => (float) $gps->latitude,
                            'longitude'   => (float) $gps->longitude,
                            'speed'       => (float) $gps->speed,
                            'recorded_at' => (string) $gps->recorded_at,
                        ] : null,
                    ];
                }

                echo "data: " . json_encode($payload) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();

                sleep(3);
                $i++;
            }
            echo "data: " . json_encode(['type' => 'close']) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
        }, 200, [
            'Content-Type'                     => 'text/event-stream',
            'Cache-Control'                    => 'no-cache',
            'X-Accel-Buffering'                => 'no',
            'Connection'                       => 'keep-alive',
            'Access-Control-Allow-Origin'      => '*',
            'Access-Control-Allow-Credentials' => 'true',
        ]);
    }

    //get antrian offline untuk device
    public function getOfflineQueue(Request $request) {
        $deviceId = $request->input('device_id', 'unknown');
        $offlineQueue = DB::table('offline_queue')->where('device_id', $deviceId)->whereNull('sent_at')->orderBy('created_at')->get();
        return $this->responseSuccess([
            'count' => count($offlineQueue),
            'data' => $offlineQueue
        ], AppMessages::SUCCESS_RETRIEVED);
    }

    //get data pending sync
    public function getPendingSyncs(Request $request) {
        $deviceId = $request->input('device_id', 'unknown');
        $pendingSyncs = OfflineDataService::getPendingSyncs($deviceId);
        return $this->responseSuccess([
            'count' => count($pendingSyncs),
            'data' => $pendingSyncs
        ], AppMessages::SUCCESS_RETRIEVED);
    }

    //Konfirmasi sync selesai
    public function confirmSync(Request $request) {
        $data = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
            'device_id' => 'required|string',
            'server_data' => 'nullable|array',
        ]);
        OfflineDataService::markAsSynced(
            $data['entity_type'],
            $data['entity_id'],
            $data['device_id'],
            $data['server_data'] ?? null
        );
        return $this->responseSuccess([], 'Sync berhasil dikonfirmasi');
    }

    //Log status GPS dari device
    public function logGpsStatus(Request $request) {
        $data = $request->validate([
            'is_enabled' => 'required|boolean',
            'has_signal' => 'required|boolean',
            'signal_strength' => 'nullable|integer|between:0,100',
            'device_id' => 'nullable|string',
        ]);
        $user = $request->user();
        DB::table('gps_health_checks')->insert([
            'user_id' => $user->id,
            'device_id' => $data['device_id'] ?? null,
            'is_gps_enabled' => $data['is_enabled'],
            'has_signal' => $data['has_signal'],
            'signal_strength' => $data['signal_strength'] ?? null,
            'connection_status' => $data['has_signal'] ? 'online' : 'weak',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $this->responseCreated([], 'Status GPS berhasil dicatat');
    }

    //get status GPS terakhir
    public function getGpsStatus(Request $request) {
        $user = $request->user();
        $lastHealth = DB::table('gps_health_checks')->where('user_id', $user->id)->orderBy('created_at', 'desc')->first();
        return $this->responseSuccess($lastHealth, AppMessages::SUCCESS_RETRIEVED);
    }

    // proses antrian offline dan retry request yang gagal
    public function processOfflineQueue(Request $request) {
        $processedCount = OfflineDataService::processPendingRequests();
        return $this->responseSuccess([
            'processed_count' => $processedCount
        ], 'Antrian offline berhasil diproses');
    }
}