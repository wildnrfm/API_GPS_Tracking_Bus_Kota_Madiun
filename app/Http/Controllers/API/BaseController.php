<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ResponseFormatter;
use Illuminate\Http\Request;

// Base controller: role checks & response formatting untuk semua API endpoints
class BaseController extends Controller
{
    use ResponseFormatter;

    protected function isAdmin(Request $request)
    {
        return $request->user()->role === 'admin';
    }

    protected function isDriver(Request $request)
    {
        return $request->user()->role === 'driver';
    }

    protected function isStudent(Request $request)
    {
        return $request->user()->role === 'siswa';
    }

    protected function authorizeAdmin(Request $request)
    {
        if (!$this->isAdmin($request)) {
            abort(403, 'Hanya admin yang dapat mengakses resource ini');
        }
    }

    protected function authorizeDriver(Request $request)
    {
        if (!$this->isDriver($request)) {
            abort(403, 'Hanya driver yang dapat mengakses resource ini');
        }
    }

    protected function authorizeStudent(Request $request)
    {
        if (!$this->isStudent($request)) {
            abort(403, 'Hanya siswa yang dapat mengakses resource ini');
        }
    }

    protected function getAuthenticatedUser(Request $request)
    {
        return $request->user();
    }
}