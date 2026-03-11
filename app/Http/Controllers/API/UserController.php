<?php

namespace App\Http\Controllers\API;

use App\Services\UserService;
use App\Constants\AppMessages;
use Illuminate\Http\Request;

// CRUD operasi user umum (penghapusan dengan cascade), Business logic dihandle di UserService
class UserController extends BaseController {
    protected $userService;
    public function __construct(UserService $userService) {
        $this->userService = $userService;
        $this->middleware('auth:api');
    }

    //GET daftar semua user
    public function index(Request $request) {
        $this->authorizeAdmin($request);
        $users = $this->userService->getAllUsers(15);
        return $this->responsePaginated($users, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //get detail user spesifik
    public function show(Request $request, $id) {
        $this->authorizeAdmin($request);
        $user = $this->userService->getUserById($id);
        if (!$user) {
            return $this->responseNotFound(AppMessages::ERROR_USER_NOT_FOUND);
        }
        return $this->responseSuccess($user, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //delete user dan data terkait (driver/student)
    public function destroy(Request $request, $id) {
        $this->authorizeAdmin($request);
        $result = $this->userService->deleteUserWithCascade($id);
        if (!$result['success']) {
            return $this->responseNotFound($result['error'] ?? AppMessages::ERROR_USER_NOT_FOUND);
        }
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }
}
