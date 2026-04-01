<?php

namespace App\Http\Controllers\API;

use App\Constants\AppMessages;
use App\Models\Route;
use App\Models\RouteHalte;
use App\Models\RoutePolyline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * RouteController — admin only (kecuali GET by bus)
 *
 * GET    /routes                   → semua rute + halte + polyline
 * GET    /routes/{id}              → detail 1 rute
 * POST   /routes                   → buat rute baru
 * PUT    /routes/{id}              → update nama rute
 * DELETE /routes/{id}              → hapus rute
 * POST   /routes/{id}/sync         → simpan polyline + halte sekaligus (dari RouteBuilder)
 * POST   /routes/{id}/polyline     → simpan/ganti titik polyline saja
 * GET    /routes/{id}/polyline     → ambil titik polyline
 * DELETE /routes/{id}/polyline     → hapus semua titik polyline
 * GET    /buses/{busId}/route      → rute bus tertentu (semua user login)
 */
class RouteController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Format data halte untuk response rute (dipakai formatRoute dan formatRouteLight).
     */
    private function formatHaltes(Route $route): array
    {
        if (!$route->routeHaltes) return [];
        return $route->routeHaltes->sortBy('urutan')->values()->map(fn($rh) => [
            'id'       => $rh->id,
            'route_id' => $rh->route_id,
            'halte_id' => $rh->halte_id,
            'urutan'   => $rh->urutan,
            'halte'    => $rh->halte ? [
                'id'         => $rh->halte->id,
                'nama_halte' => $rh->halte->nama_halte,
                'alamat'     => $rh->halte->alamat,
                'latitude'   => (float) $rh->halte->latitude,
                'longitude'  => (float) $rh->halte->longitude,
            ] : null,
        ])->values()->toArray();
    }

    /**
     * Format lengkap rute termasuk polyline (untuk show/getByBus).
     */
    private function formatRoute(Route $route): array
    {
        return [
            'id'        => $route->id,
            'bus_id'    => $route->bus_id,
            'nama_rute' => $route->nama_rute,
            'bus'       => $route->bus ? [
                'id'         => $route->bus->id,
                'kode_bus'   => $route->bus->kode_bus,
                'plat_nomor' => $route->bus->plat_nomor,
            ] : null,
            'haltes'     => $this->formatHaltes($route),
            'polyline'   => $route->polylines
                ? $route->polylines->sortBy('urutan')->values()->map(fn($p) => [
                    'urutan'    => $p->urutan,
                    'latitude'  => (float) $p->latitude,
                    'longitude' => (float) $p->longitude,
                ])->values()->toArray()
                : [],
            'created_at' => $route->created_at,
            'updated_at' => $route->updated_at,
        ];
    }

    /**
     * Format ringkas rute untuk listing (tanpa polyline agar response ringan).
     */
    private function formatRouteLight(Route $route): array
    {
        return [
            'id'         => $route->id,
            'bus_id'     => $route->bus_id,
            'nama_rute'  => $route->nama_rute,
            'bus'        => $route->bus ? [
                'id'         => $route->bus->id,
                'kode_bus'   => $route->bus->kode_bus,
                'plat_nomor' => $route->bus->plat_nomor,
            ] : null,
            'haltes'     => $this->formatHaltes($route),
            'polyline'   => [], // tidak dimuat di listing, fetch via show() jika perlu
            'created_at' => $route->created_at,
            'updated_at' => $route->updated_at,
        ];
    }

    // ─── CRUD ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        // Tidak load polylines di index agar response ringan
        // Polyline hanya dimuat di show() dan getByBus()
        $routes = Route::with([
            'bus:id,kode_bus,plat_nomor',
            'routeHaltes.halte',
        ])->get();
        return $this->responseSuccess(
            $routes->map(fn($r) => $this->formatRouteLight($r))->values(),
            AppMessages::SUCCESS_RETRIEVED
        );
    }

    public function show(Request $request, $id)
    {
        $route = Route::with([
            'bus:id,kode_bus,plat_nomor',
            'routeHaltes.halte',
            'polylines',
        ])->findOrFail($id);
        return $this->responseSuccess($this->formatRoute($route), AppMessages::SUCCESS_RETRIEVED);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bus_id'    => 'required|exists:buses,id',
            'nama_rute' => 'required|string|max:150',
        ], [
            'bus_id.required'    => 'Bus wajib dipilih',
            'bus_id.exists'      => 'Bus tidak ditemukan',
            'nama_rute.required' => 'Nama rute wajib diisi',
        ]);

        if (Route::where('bus_id', $data['bus_id'])->exists()) {
            return $this->responseError('Bus ini sudah memiliki rute.', null, 422);
        }

        $route = Route::create($data);
        $route->load(['bus:id,kode_bus,plat_nomor', 'routeHaltes.halte', 'polylines']);
        return $this->responseCreated($this->formatRoute($route), AppMessages::SUCCESS_CREATED);
    }

    public function update(Request $request, $id)
    {
        $route = Route::findOrFail($id);
        $data = $request->validate([
            'nama_rute' => 'sometimes|string|max:150',
            'bus_id'    => 'sometimes|exists:buses,id',
        ]);

        if (isset($data['bus_id']) && $data['bus_id'] != $route->bus_id) {
            if (Route::where('bus_id', $data['bus_id'])->where('id', '!=', $id)->exists()) {
                return $this->responseError('Bus sudah dipakai rute lain.', null, 422);
            }
        }

        $route->update($data);
        $route->load(['bus:id,kode_bus,plat_nomor', 'routeHaltes.halte', 'polylines']);
        return $this->responseUpdated($this->formatRoute($route), AppMessages::SUCCESS_UPDATED);
    }

    public function destroy(Request $request, $id)
    {
        Route::findOrFail($id)->delete();
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }

    // ─── Sync dari RouteBuilderScreen ────────────────────────

    /**
     * POST /routes/{id}/sync
     *
     * Body:
     * {
     *   "nama_rute": "Halte A → Halte D",    (opsional)
     *   "polyline": [                          (wajib, min 2)
     *     {"latitude": -7.62, "longitude": 111.52},
     *     ...
     *   ],
     *   "haltes": [                            (opsional)
     *     {"halte_id": 1, "urutan": 1},
     *     ...
     *   ]
     * }
     */
    public function syncRoute(Request $request, $id)
    {
        $route = Route::findOrFail($id);

        $data = $request->validate([
            'nama_rute'              => 'sometimes|string|max:150',
            'polyline'               => 'required|array|min:2',
            'polyline.*.latitude'    => 'required|numeric|between:-90,90',
            'polyline.*.longitude'   => 'required|numeric|between:-180,180',
            'haltes'                 => 'sometimes|array',
            'haltes.*.halte_id'      => 'required_with:haltes|integer|exists:haltes,id',
            'haltes.*.urutan'        => 'required_with:haltes|integer|min:1',
        ], [
            'polyline.required' => 'Data polyline wajib ada',
            'polyline.min'      => 'Minimal 2 titik polyline',
        ]);

        DB::transaction(function () use ($route, $data) {
            if (!empty($data['nama_rute'])) {
                $route->update(['nama_rute' => $data['nama_rute']]);
            }

            // Replace polyline
            RoutePolyline::where('route_id', $route->id)->delete();
            $polyRows = [];
            foreach ($data['polyline'] as $i => $pt) {
                $polyRows[] = [
                    'route_id'   => $route->id,
                    'urutan'     => $i + 1,
                    'latitude'   => $pt['latitude'],
                    'longitude'  => $pt['longitude'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($polyRows) RoutePolyline::insert($polyRows);

            // Replace halte jika dikirim
            if (!empty($data['haltes'])) {
                RouteHalte::where('route_id', $route->id)->delete();
                $halteRows = [];
                foreach ($data['haltes'] as $h) {
                    $halteRows[] = [
                        'route_id'   => $route->id,
                        'halte_id'   => $h['halte_id'],
                        'urutan'     => $h['urutan'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($halteRows) RouteHalte::insert($halteRows);
            }
        });

        $route->load(['bus:id,kode_bus,plat_nomor', 'routeHaltes.halte', 'polylines']);
        return $this->responseSuccess($this->formatRoute($route), 'Rute berhasil disimpan');
    }

    // ─── Polyline saja ────────────────────────────────────────

    public function storePolyline(Request $request, $id)
    {
        $route = Route::findOrFail($id);

        $data = $request->validate([
            'points'              => 'required|array|min:2',
            'points.*.latitude'   => 'required|numeric|between:-90,90',
            'points.*.longitude'  => 'required|numeric|between:-180,180',
        ], ['points.min' => 'Minimal 2 titik jalur']);

        DB::transaction(function () use ($route, $data) {
            RoutePolyline::where('route_id', $route->id)->delete();
            $rows = [];
            foreach ($data['points'] as $i => $pt) {
                $rows[] = [
                    'route_id'   => $route->id,
                    'urutan'     => $i + 1,
                    'latitude'   => $pt['latitude'],
                    'longitude'  => $pt['longitude'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            RoutePolyline::insert($rows);
        });

        $polylines = RoutePolyline::where('route_id', $route->id)->orderBy('urutan')->get(['urutan', 'latitude', 'longitude']);
        return $this->responseSuccess($polylines, 'Polyline berhasil disimpan');
    }

    public function getPolyline(Request $request, $id)
    {
        Route::findOrFail($id);
        $polylines = RoutePolyline::where('route_id', $id)->orderBy('urutan')->get(['urutan', 'latitude', 'longitude']);
        return $this->responseSuccess($polylines, AppMessages::SUCCESS_RETRIEVED);
    }

    public function destroyPolyline(Request $request, $id)
    {
        Route::findOrFail($id);
        RoutePolyline::where('route_id', $id)->delete();
        return $this->responseDeleted('Polyline berhasil dihapus');
    }


    // ─── Sync halte saja (tanpa polyline) ────────────────────

    /**
     * POST /routes/{id}/haltes/sync
     * Body: { "haltes": [{"halte_id": 1, "urutan": 1}, ...] }
     */
    public function syncHaltes(Request $request, $id)
    {
        $route = Route::findOrFail($id);

        $data = $request->validate([
            'haltes'            => 'required|array|min:1',
            'haltes.*.halte_id' => 'required|integer|exists:haltes,id',
            'haltes.*.urutan'   => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($route, $data) {
            RouteHalte::where('route_id', $route->id)->delete();
            $rows = [];
            foreach ($data['haltes'] as $h) {
                $rows[] = [
                    'route_id'   => $route->id,
                    'halte_id'   => $h['halte_id'],
                    'urutan'     => $h['urutan'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows) RouteHalte::insert($rows);
        });

        $route->load(['bus:id,kode_bus,plat_nomor', 'routeHaltes.halte', 'polylines']);
        return $this->responseSuccess($this->formatRoute($route), 'Halte rute berhasil disimpan');
    }

    // ─── Endpoint untuk siswa & driver ───────────────────────

    public function getByBus(Request $request, $busId)
    {
        $route = Route::with([
            'bus:id,kode_bus,plat_nomor',
            'routeHaltes.halte',
            'polylines',
        ])->where('bus_id', $busId)->first();

        if (!$route) {
            return $this->responseNotFound('Bus ini belum memiliki rute');
        }
        return $this->responseSuccess($this->formatRoute($route), AppMessages::SUCCESS_RETRIEVED);
    }
}