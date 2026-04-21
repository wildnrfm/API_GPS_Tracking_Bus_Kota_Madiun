<?php

namespace App\Http\Controllers\API;

use App\Models\GpsTrack;
use App\Models\Bus;
use App\Models\BusDriver;
use App\Services\OfflineDataService;
use App\Constants\AppMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            'latitude'         => 'required|numeric|between:-90,90',
            'longitude'        => 'required|numeric|between:-180,180',
            'speed'            => 'nullable|numeric|min:0',
            'accuracy'         => 'nullable|numeric|min:0',
            'heading'          => 'nullable|numeric|between:0,360',
            'device_timestamp' => 'nullable|integer',
        ], [
            'latitude.required' => 'Latitude harus diisi',
            'latitude.between'  => 'Latitude harus antara -90 dan 90',
            'longitude.required'=> 'Longitude harus diisi',
            'longitude.between' => 'Longitude harus antara -180 dan 180',
            'speed.numeric'     => 'Kecepatan harus berupa angka',
        ]);

        // Tolak data GPS dengan akurasi sangat buruk (> 50 meter)
        // Ini menghindari lokasi yang melompat jauh saat sinyal lemah
        $accuracy = $data['accuracy'] ?? null;
        if ($accuracy !== null && $accuracy > 50) {
            return $this->responseSuccess([
                'skipped' => true,
                'reason'  => 'Akurasi GPS terlalu rendah (' . round($accuracy) . 'm)',
            ], 'Data GPS dilewati karena akurasi rendah');
        }

        // recorded_at = server time (now()) agar urutan query MAX(recorded_at) akurat
        // device_timestamp = waktu dari perangkat untuk referensi (bisa beda timezone)
        $deviceTs = isset($data['device_timestamp'])
            ? Carbon::createFromTimestampMs($data['device_timestamp'])
            : now();

        // Speed negatif dari geolocator artinya tidak tersedia — set 0
        $speed = max(0, $data['speed'] ?? 0);

        $gpsTrack = GpsTrack::create([
            'bus_id'           => $busId,
            'latitude'         => $data['latitude'],
            'longitude'        => $data['longitude'],
            'speed'            => round($speed, 2),
            'accuracy'         => $accuracy ? round($accuracy, 1) : null,
            'heading'          => isset($data['heading']) ? round($data['heading'], 1) : null,
            'device_timestamp' => $deviceTs,
            'recorded_at'      => now(), // selalu server time untuk konsistensi sorting
        ]);

        // Update last_gps_update agar auto-reset 5 menit tidak salah terpicu
        // saat driver aktif tapi tidak bergerak (distanceFilter)
        $activeAssignment->update(['last_gps_update' => now()]);

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
        $today = now()->toDateString();
        $now   = now();

        // Load semua bus sekaligus (hindari N+1)
        $buses = Bus::select('id', 'kode_bus', 'plat_nomor')->get();
        $busIds = $buses->pluck('id');

        // Load GPS terbaru per bus — pakai MAX(recorded_at) bukan MAX(id)
        // karena id tidak menjamin urutan waktu (device_timestamp bisa berbeda)
        // PENTING: Filter whereDate('recorded_at', $today) agar tidak pakai
        // koordinat dari sesi kemarin/minggu lalu saat driver baru toggle ON
        $latestGpsMap = GpsTrack::select('bus_id',
                DB::raw('MAX(recorded_at) as max_recorded'))
            ->whereIn('bus_id', $busIds)
            ->whereDate('recorded_at', $today)
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0)
            ->groupBy('bus_id')
            ->get()
            ->pluck('max_recorded', 'bus_id');

        // Ambil record yang sesuai dengan recorded_at terbaru per bus
        $gpsRecords = collect();
        foreach ($latestGpsMap as $busId => $maxRecorded) {
            $record = GpsTrack::where('bus_id', $busId)
                ->where('recorded_at', $maxRecorded)
                ->whereDate('recorded_at', $today)
                ->where('latitude', '!=', 0)
                ->where('longitude', '!=', 0)
                ->first(['id', 'bus_id', 'latitude', 'longitude', 'speed', 'accuracy', 'heading', 'recorded_at']);
            if ($record) $gpsRecords->put($busId, $record);
        }

        // Load assignment aktif per bus dalam 1 query
        // Urutkan: gps_status='on' dulu, lalu terbaru — agar saat keyBy('bus_id')
        // assignment yang aktif GPS-nya yang menang jika ada lebih dari 1 per bus
        $assignments = BusDriver::select('id', 'bus_id', 'driver_id', 'gps_status', 'last_gps_update')
            ->whereIn('bus_id', $busIds)
            ->where(function($q) use ($today) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $today);
            })
            ->with('driver:id,user_id,no_hp', 'driver.user:id,name')
            ->orderByRaw("CASE WHEN gps_status = 'on' THEN 0 ELSE 1 END")
            ->orderBy('last_gps_update', 'desc')
            ->get()
            ->keyBy('bus_id');

        // Auto-reset GPS yang stale:
        // - gps_status = 'on' tapi last_gps_update NULL (sesi lama yang tidak pernah kirim koordinat)
        // - gps_status = 'on' tapi last_gps_update sudah > 10 menit yang lalu
        // CATATAN: Threshold dinaikkan dari 5 → 10 menit karena heartbeat Flutter setiap 2 menit
        // + delay jaringan bisa membuat GPS ter-reset secara salah saat driver masih aktif
        $staleIds = $assignments->filter(function($a) use ($now) {
            if ($a->gps_status !== 'on') return false;
            // NULL last_gps_update = toggle ON dari sesi lama, langsung reset
            if (!$a->last_gps_update) return true;
            // Ada last_gps_update tapi sudah > 10 menit
            return $now->diffInMinutes(Carbon::parse($a->last_gps_update)) > 10;
        })->pluck('id');

        if ($staleIds->isNotEmpty()) {
            BusDriver::whereIn('id', $staleIds)->update(['gps_status' => 'off']);
            $assignments->whereIn('id', $staleIds->all())
                ->each(fn($a) => $a->gps_status = 'off');
        }

        $dashboardData = $buses->map(function ($bus) use ($gpsRecords, $assignments) {
            $gps        = $gpsRecords->get($bus->id);
            $assignment = $assignments->get($bus->id);
            $gpsStatus  = $assignment?->gps_status ?? 'off';

            return [
                'bus_id'          => $bus->id,
                'bus_code'        => $bus->kode_bus,
                'bus_plate'       => $bus->plat_nomor,
                'gps_status'      => $gpsStatus,
                'last_gps_update' => $assignment?->last_gps_update,
                // Kirim current_position HANYA jika GPS sedang aktif (on)
                // Jika off, kirim null agar Flutter tidak tampilkan marker
                'current_position' => ($gpsStatus === 'on' && $gps) ? [
                    'latitude'    => (float) $gps->latitude,
                    'longitude'   => (float) $gps->longitude,
                    'speed'       => (float) $gps->speed,
                    'accuracy'    => $gps->accuracy ? (float) $gps->accuracy : null,
                    'heading'     => $gps->heading ? (float) $gps->heading : null,
                    'recorded_at' => (string) $gps->recorded_at,
                ] : null,
                'driver' => $assignment?->driver?->user ? [
                    'id'    => $assignment->driver->id,
                    'name'  => $assignment->driver->user->name,
                    'phone' => $assignment->driver->no_hp,
                ] : null,
            ];
        })->values();

        return $this->responseSuccess([
            'count'     => $dashboardData->count(),
            'data'      => $dashboardData,
            'timestamp' => now(),
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