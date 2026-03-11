<?php

namespace App\Traits;

trait ResponseFormatter {
    protected function responseSuccess($data = null, $message = 'Operasi berhasil', $statusCode = 200) {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    protected function responseError($message = 'Operasi gagal', $errors = null, $statusCode = 400){
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $statusCode);
    }

    protected function responseCreated($data, $message = 'Resource berhasil dibuat') {
        return $this->responseSuccess($data, $message, 201);
    }

    protected function responseUpdated($data, $message = 'Resource berhasil diperbarui') {
        return $this->responseSuccess($data, $message, 200);
    }

    protected function responseDeleted($message = 'Resource berhasil dihapus') {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], 200);
    }

    protected function responseNotFound($message = 'Resource tidak ditemukan') {
        return $this->responseError($message, null, 404);
    }

    protected function responseForbidden($message = 'Akses ditolak') {
        return $this->responseError($message, null, 403);
    }

    protected function responseUnauthorized($message = 'Tidak terautentikasi') {
        return $this->responseError($message, null, 401);
    }

    protected function responseValidationError($errors, $message = 'Validasi gagal') {
        return $this->responseError($message, $errors, 422);
    }

    protected function responseConflict($messageOrData = 'Data sudah ada', $message = null) {
        $response = [
            'success' => false,
        ];
        if (is_array($messageOrData)) {
            $response['message'] = $message ?? 'Data sudah ada';
            $response['data'] = $messageOrData;
        } else {
            $response['message'] = $messageOrData;
        }
        return response()->json($response, 409);
    }

    protected function responsePaginated($items, $message = 'Data berhasil diambil') {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ]
        ], 200);
    }
}
