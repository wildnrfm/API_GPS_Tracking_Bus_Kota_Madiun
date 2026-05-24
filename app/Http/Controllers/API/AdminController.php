<?php

namespace App\Http\Controllers\API;

use App\Services\UserService;
use App\Constants\AppMessages;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// CRUD admin, system diagnostics) - Business logic dihandle di UserService
class AdminController extends BaseController {
    protected $userService;
    public function __construct(UserService $userService) {
        $this->userService = $userService;
        $this->middleware('auth:api');
    }

    // GET daftar semua admin
    public function index(Request $request) {
        $admins = User::where('role', 'admin')->paginate(15);
        return $this->responsePaginated($admins, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // GET detail admin
    public function show(Request $request, $id) {
        $admin = $this->userService->getUserById($id);
        if (!$admin || $admin->role !== 'admin') {
            return $this->responseNotFound(AppMessages::ERROR_USER_NOT_FOUND);
        }
        return $this->responseSuccess($admin, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // Create admin
    public function store(Request $request) {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => 'required|string|min:8|confirmed',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'name.required' => AppMessages::ERROR_NAME_REQUIRED,
            'name.max' => AppMessages::ERROR_NAME_TOO_LONG,
            'email.required' => AppMessages::ERROR_EMAIL_REQUIRED,
            'email.email' => AppMessages::ERROR_EMAIL_INVALID,
            'email.unique' => AppMessages::ERROR_EMAIL_TAKEN,
            'password.required' => AppMessages::ERROR_PASSWORD_REQUIRED,
            'password.min' => AppMessages::ERROR_PASSWORD_WEAK,
            'password.confirmed' => AppMessages::ERROR_PASSWORD_MISMATCH,
            'photo.image' => 'File harus berupa gambar',
            'photo.mimes' => 'Format gambar harus jpeg, png, jpg, atau gif',
            'photo.max' => 'Ukuran gambar maksimal 2MB',
        ]);

        if ($request->hasFile('photo')) {
            $photo    = $request->file('photo');
            $filename = uniqid('admin_', true) . '.' . $photo->getClientOriginalExtension();
            $destDir  = public_path('images/admin');
            if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
            $photo->move($destDir, $filename);
            $data['photo'] = 'images/admin/' . $filename;
        }

        $result = $this->userService->createAdmin($data);
        if (!$result['success']) {
            return $this->responseError($result['error'], null, 500);
        }
        return $this->responseCreated($result['user'], AppMessages::SUCCESS_CREATED);
    }

    //update data admin
    public function update(Request $request, $id) {
        $rules = [];
        $messages = [];
        if ($request->has('name')) {
            $rules['name'] = 'required|string|max:255';
            $messages['name.required'] = AppMessages::ERROR_NAME_REQUIRED;
            $messages['name.max'] = AppMessages::ERROR_NAME_TOO_LONG;
        }
        if ($request->has('email')) {
            $rules['email'] = ['required', 'email', Rule::unique('users', 'email')->ignore($id)];
            $messages['email.required'] = AppMessages::ERROR_EMAIL_REQUIRED;
            $messages['email.email'] = AppMessages::ERROR_EMAIL_INVALID;
            $messages['email.unique'] = AppMessages::ERROR_EMAIL_TAKEN;
        }
        if ($request->has('password')) {
            $rules['password'] = 'required|string|min:8|confirmed';
            $rules['password_confirmation'] = 'required|string';
            $messages['password.required'] = AppMessages::ERROR_PASSWORD_REQUIRED;
            $messages['password.min'] = AppMessages::ERROR_PASSWORD_WEAK;
            $messages['password.confirmed'] = AppMessages::ERROR_PASSWORD_MISMATCH;
            $messages['password_confirmation.required'] = 'Password confirmation harus diisi';
        }
        if ($request->hasFile('photo') || $request->has('photo')) {
            $rules['photo'] = 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048';
            $messages['photo.image'] = 'File harus berupa gambar';
            $messages['photo.mimes'] = 'Format gambar harus jpeg, png, jpg, atau gif';
            $messages['photo.max'] = 'Ukuran gambar maksimal 2MB';
        }
        if (empty($rules)) {
            return $this->responseError('Tidak ada data yang dapat diupdate', null, 422);
        }
        $data = $request->validate($rules, $messages);

        if ($request->hasFile('photo')) {
            $admin = User::where('role', 'admin')->findOrFail($id);
            if ($admin->photo && file_exists(public_path($admin->photo))) {
                @unlink(public_path($admin->photo));
            }
            $photo    = $request->file('photo');
            $filename = uniqid('admin_', true) . '.' . $photo->getClientOriginalExtension();
            $destDir  = public_path('images/admin');
            if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
            $photo->move($destDir, $filename);
            $data['photo'] = 'images/admin/' . $filename;
        }

        $result = $this->userService->updateAdmin($id, $data);
        if (!$result['success']) {
            return $this->responseNotFound($result['error'] ?? AppMessages::ERROR_USER_NOT_FOUND);
        }
        return $this->responseUpdated($result['user'], AppMessages::SUCCESS_UPDATED);
    }

    // delete admin
    public function destroy(Request $request, $id) {
        $result = $this->userService->deleteAdmin($id);
        if (!$result['success']) {
            return $this->responseNotFound($result['error'] ?? AppMessages::ERROR_USER_NOT_FOUND);
        }
        return $this->responseDeleted(AppMessages::SUCCESS_DELETED);
    }
}
