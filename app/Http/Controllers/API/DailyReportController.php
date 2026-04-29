<?php

namespace App\Http\Controllers\API;

use App\Models\DailyReport;
use App\Models\Bus;
use App\Models\BusDriver;
use App\Models\Attendance;
use App\Constants\AppMessages;
use Illuminate\Http\Request;

class DailyReportController extends BaseController {
    public function __construct() {
        $this->middleware('auth:api');
    }

    // Driver submit laporan selesai bertugas
    public function store(Request $request) {
        $data = $request->validate([
            'bus_id'          => 'required|exists:buses,id',
            'tanggal'         => 'required|date_format:Y-m-d',
            'total_penumpang' => 'required|integer|min:0',
            'catatan_driver'  => 'nullable|string|max:1000',
        ], [
            'bus_id.required'          => 'ID bus harus diisi',
            'bus_id.exists'            => 'Bus tidak ditemukan',
            'tanggal.required'         => 'Tanggal harus diisi',
            'total_penumpang.required' => 'Total penumpang harus diisi',
        ]);

        // Cegah duplikat laporan di hari yang sama
        $existing = DailyReport::where('bus_id', $data['bus_id'])
            ->whereDate('tanggal', $data['tanggal'])
            ->first();

        if ($existing) {
            return $this->responseError(
                'Laporan untuk bus ini pada tanggal ' . $data['tanggal'] . ' sudah ada.',
                422
            );
        }

        $bus = Bus::findOrFail($data['bus_id']);

        // Cari bus_driver aktif hari ini
        $activeBusDriver = BusDriver::where('bus_id', $data['bus_id'])
            ->where('tanggal_mulai', '<=', $data['tanggal'])
            ->where(function ($q) use ($data) {
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $data['tanggal']);
            })
            ->with('driver.user')
            ->orderByDesc('tanggal_mulai')
            ->first();

        $report = DailyReport::create([
            'bus_id'          => $data['bus_id'],
            'tanggal'         => $data['tanggal'],
            'total_penumpang' => $data['total_penumpang'],
            'catatan_driver'  => $data['catatan_driver'] ?? null,
            'bus_driver_id'   => $activeBusDriver?->id,
        ]);

        return $this->responseCreated([
            'id'           => $report->id,
            'bus_id'       => $report->bus_id,
            'bus_code'     => $bus->kode_bus,
            'bus_plate'    => $bus->plat_nomor,
            'tanggal'      => $report->tanggal,
            'total_penumpang' => $report->total_penumpang,
            'catatan_driver'  => $report->catatan_driver,
            'driver_name'  => $activeBusDriver?->driver?->user?->name ?? '-',
        ], AppMessages::SUCCESS_CREATED);
    }

    // List laporan
    public function index(Request $request) {
        $query = DailyReport::with(['bus', 'busDriver.driver.user']);

        if ($request->has('bus_id')) {
            $query->where('bus_id', $request->input('bus_id'));
        }
        if ($request->has('tanggal')) {
            $query->whereDate('tanggal', $request->input('tanggal'));
        }
        if ($request->has('date_from')) {
            $query->whereDate('tanggal', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('tanggal', '<=', $request->input('date_to'));
        }

        $reports = $query->orderBy('tanggal', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->responsePaginated($reports, AppMessages::SUCCESS_RETRIEVED);
    }

    // Detail laporan
    public function show($id) {
        $report = DailyReport::with(['bus', 'busDriver.driver.user'])->findOrFail($id);

        $attendances = Attendance::where('bus_id', $report->bus_id)
            ->whereDate('tanggal', $report->tanggal)
            ->with('student.user')
            ->get();

        return $this->responseSuccess([
            'id'              => $report->id,
            'bus_id'          => $report->bus_id,
            'bus_code'        => $report->bus->kode_bus,
            'bus_plate'       => $report->bus->plat_nomor,
            'tanggal'         => $report->tanggal,
            'total_penumpang' => $report->total_penumpang,
            'catatan_driver'  => $report->catatan_driver,
            'driver_name'     => $report->busDriver?->driver?->user?->name ?? '-',
            'penumpang'       => $attendances->map(fn($a) => [
                'nama'       => $a->student?->user?->name,
                'waktu_naik' => $a->waktu_naik,
                'checkout'   => $a->waktu_turun ? 'Yes' : 'No',
            ]),
        ], AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // Update catatan saja
    public function update(Request $request, $id) {
        $report = DailyReport::findOrFail($id);
        $data   = $request->validate([
            'catatan_driver'  => 'nullable|string|max:1000',
            'total_penumpang' => 'sometimes|integer|min:0',
        ]);
        $report->update($data);
        return $this->responseSuccess($report, AppMessages::SUCCESS_UPDATED);
    }

    // Hapus laporan
    public function destroy($id) {
        DailyReport::findOrFail($id)->delete();
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }
}