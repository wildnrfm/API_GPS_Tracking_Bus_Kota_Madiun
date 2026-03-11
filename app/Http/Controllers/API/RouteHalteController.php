<?php

namespace App\Http\Controllers\API;

use App\Constants\AppMessages;
use App\Models\Route;
use App\Models\RouteHalte;
use Illuminate\Http\Request;

//admin only
class RouteHalteController extends BaseController {
    public function __construct() {
        $this->middleware('auth:api');
    }

    //post halte ke rute
    public function storeHalteToRoute(Request $request, $routeId) {
        $this->authorizeAdmin($request);
        Route::findOrFail($routeId);
        $data = $request->validate([
            'halte_id' => 'required|exists:haltes,id',
            'urutan' => 'required|integer|min:1',
        ], [
            'halte_id.required' => 'Halte wajib diisi',
            'halte_id.exists' => 'Halte tidak ditemukan',
            'urutan.required' => 'Urutan wajib diisi',
            'urutan.integer' => 'Urutan harus berupa angka',
            'urutan.min' => 'Urutan minimal 1',
        ]);
        $existingHalte = RouteHalte::where('route_id', $routeId)->where('halte_id', $data['halte_id'])->first();
        if ($existingHalte) {
            return $this->responseError('Halte sudah ada di rute ini', 422);
        }
        $existingOrder = RouteHalte::where('route_id', $routeId)->where('urutan', $data['urutan'])->first();
        if ($existingOrder) {
            return $this->responseError('Urutan sudah digunakan di rute ini', 422);
        }
        $routeHalte = RouteHalte::create([
            'route_id' => $routeId,
            'halte_id' => $data['halte_id'],
            'urutan' => $data['urutan'],
        ]);
        return $this->responseCreated($routeHalte, AppMessages::SUCCESS_CREATED);
    }

    //update urutan halte dalam rute
    public function update(Request $request, $id) {
        $this->authorizeAdmin($request);
        $routeHalte = RouteHalte::findOrFail($id);
        $data = $request->validate([
            'urutan' => 'sometimes|integer|min:1',
        ], [
            'urutan.integer' => 'Urutan harus berupa angka',
            'urutan.min' => 'Urutan minimal 1',
        ]);
        if (isset($data['urutan'])) {
            $existingOrder = RouteHalte::where('route_id', $routeHalte->route_id)->where('urutan', $data['urutan'])->where('id', '!=', $id)->first();
            if ($existingOrder) {
                return $this->responseError('Urutan sudah digunakan di rute ini', 422);
            }
        }
        $routeHalte->update($data);
        return $this->responseUpdated($routeHalte, AppMessages::SUCCESS_UPDATED);
    }

    //delete halte dari rute
    public function destroy(Request $request, $id) {
        $this->authorizeAdmin($request);
        $routeHalte = RouteHalte::findOrFail($id);
        $routeHalte->delete();
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }

    //get daftar halte berdasarkan rute
    public function getHaltesByRoute($routeId) {
        $haltes = RouteHalte::where('route_id', $routeId)->with('halte')->orderBy('urutan')->get();
        return $this->responseSuccess($haltes, AppMessages::SUCCESS_RETRIEVED);
    }
}
