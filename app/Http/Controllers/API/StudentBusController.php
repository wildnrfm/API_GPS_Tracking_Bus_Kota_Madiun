<?php

namespace App\Http\Controllers\API;

use App\Constants\AppMessages;
use App\Models\StudentBus;
use App\Models\Bus;
use Illuminate\Http\Request;

//admin only
class StudentBusController extends BaseController {
    public function __construct() {
        $this->middleware('auth:api');
    }

    //get daftar penugasan siswa ke bus
    public function index(Request $request) {
        $assignments = StudentBus::with('student.user', 'bus', 'halte')->paginate(15);
        return $this->responsePaginated($assignments, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //post siswa ke bus (jika sudah ada assignment, update saja)
    public function assignStudentToBus(Request $request, $busId) {
        Bus::findOrFail($busId);
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'halte_id'   => 'required|exists:haltes,id',
        ], [
            'student_id.required' => 'Siswa wajib diisi',
            'student_id.exists'   => 'Siswa tidak ditemukan',
            'halte_id.required'   => 'Halte wajib diisi',
            'halte_id.exists'     => 'Halte tidak ditemukan',
        ]);

        // Jika siswa sudah punya assignment, UPDATE ke bus/halte baru
        // Admin bisa re-assign siswa ke bus berbeda kapan saja (termasuk saat approve)
        $existingAssignment = StudentBus::where('student_id', $data['student_id'])->first();
        if ($existingAssignment) {
            $existingAssignment->update([
                'bus_id'   => $busId,
                'halte_id' => $data['halte_id'],
            ]);
            return $this->responseSuccess(
                $existingAssignment->load('student.user', 'bus', 'halte'),
                'Penugasan siswa berhasil diperbarui'
            );
        }

        // Buat assignment baru
        $studentBus = StudentBus::create([
            'student_id' => $data['student_id'],
            'bus_id'     => $busId,
            'halte_id'   => $data['halte_id'],
        ]);
        return $this->responseCreated(
            $studentBus->load('student.user', 'bus', 'halte'),
            AppMessages::MSG_STUDENT_ASSIGNED_TO_BUS
        );
    }

    //update penugasan siswa (change halte)
    public function update(Request $request, $busId, $studentId) {
        Bus::findOrFail($busId);
        $studentBus = StudentBus::where('bus_id', $busId)->where('student_id', $studentId)->firstOrFail();
        $data = $request->validate([
            'halte_id' => 'required|exists:haltes,id',
        ], [
            'halte_id.required' => 'Halte wajib diisi',
            'halte_id.exists' => 'Halte tidak ditemukan',
        ]);
        $studentBus->update($data);
        return $this->responseUpdated(
            $studentBus->load('student.user', 'bus', 'halte'),
            AppMessages::SUCCESS_UPDATED
        );
    }

    //delete penugasan siswa dari bus
    public function destroy(Request $request, $busId, $studentId) {
        Bus::findOrFail($busId);
        $studentBus = StudentBus::where('bus_id', $busId)->where('student_id', $studentId)->firstOrFail();
        $studentBus->delete();
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }
}