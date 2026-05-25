<?php

namespace App\Http\Controllers\API;

use App\Constants\AppMessages;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

// log aktivitas dan dashboard keamanan - admin only
class ActivityController extends BaseController {
    public function __construct() {
        $this->middleware('auth:api');
    }

    public function index(Request $request) {
        $query = ActivityLog::with('user');
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }
        $perPage = $request->input('per_page', 50);
        $logs = $query->latest()->paginate($perPage);
        return $this->responsePaginated($logs, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // get security dashboard summary
    public function dashboard(Request $request) {
        $startDate = null;
        $endDate = null;

        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = \Carbon\Carbon::parse($request->start_date)->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->end_date)->endOfDay();
        } elseif ($request->has('date')) {
            $startDate = \Carbon\Carbon::parse($request->date)->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->date)->endOfDay();
        }

        // Recent logins count
        $recentLoginsQuery = ActivityLog::where('action', 'login')->where('status', 'success');
        if ($startDate && $endDate) {
            $recentLoginsQuery->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $recentLoginsQuery->where('created_at', '>', now()->subHours(24));
        }
        $recentLogins = $recentLoginsQuery->count();

        // Failed login attempts
        $failedLoginsQuery = ActivityLog::where('action', 'like', 'login%')->where('status', 'failed');
        if ($startDate && $endDate) {
            $failedLoginsQuery->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $failedLoginsQuery->where('created_at', '>', now()->subHours(24));
        }
        $failedLogins = $failedLoginsQuery->count();

        // Blocked logins
        $blockedLoginsQuery = ActivityLog::where('action', 'login_blocked');
        if ($startDate && $endDate) {
            $blockedLoginsQuery->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $blockedLoginsQuery->where('created_at', '>', now()->subHours(24));
        }
        $blockedLogins = $blockedLoginsQuery->count();

        // Suspended accounts - always current count
        $suspendedAccounts = User::where('is_suspended', true)->count();

        // Top 10 most active users
        $activeUsersQuery = ActivityLog::query();
        if ($startDate && $endDate) {
            $activeUsersQuery->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $activeUsersQuery->where('created_at', '>', now()->subDays(7));
        }
        $activeUsers = $activeUsersQuery->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as activity_count')
            ->with('user:id,name,email,role')
            ->orderByDesc('activity_count')
            ->limit(10)
            ->get();

        // Recent failed logins
        $recentFailuresQuery = ActivityLog::where('action', 'login_failed');
        if ($startDate && $endDate) {
            $recentFailuresQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $recentFailures = $recentFailuresQuery->with('user')->latest()->limit(10)->get();

        // Activity by type
        $activityByTypeQuery = ActivityLog::query();
        if ($startDate && $endDate) {
            $activityByTypeQuery->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $activityByTypeQuery->where('created_at', '>', now()->subDays(30));
        }
        $activityByType = $activityByTypeQuery->groupBy('action')
            ->selectRaw('action, COUNT(*) as count')
            ->orderByDesc('count')
            ->get();

        $data = [
            'summary' => [
                'recent_logins_24h' => $recentLogins,
                'failed_logins_24h' => $failedLogins,
                'blocked_logins_24h' => $blockedLogins,
                'suspended_accounts' => $suspendedAccounts,
            ],
            'top_active_users' => $activeUsers,
            'recent_failed_logins' => $recentFailures,
            'activity_by_type' => $activityByType,
        ];
        return $this->responseSuccess($data, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    //get user activity summary
    public function userActivity(Request $request, $userId) {
        $user = User::find($userId);
        if (!$user) {
            return $this->responseNotFound(AppMessages::ERROR_USER_NOT_FOUND);
        }
        $activities = ActivityLog::where('user_id', $userId)->selectRaw('action, status, COUNT(*) as count')->groupBy('action', 'status')->get();
        $recentActivity = ActivityLog::where('user_id', $userId)->latest()->limit(20)->get();
        $data = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'last_login_at' => $user->last_login_at,
                'last_login_ip' => $user->last_login_ip,
                'is_suspended' => $user->is_suspended,
            ],
            'activity_summary' => $activities,
            'recent_activity' => $recentActivity,
        ];
        return $this->responseSuccess($data, AppMessages::SUCCESS_DATA_RETRIEVED);
    }

    // Export activity logs as CSV (excel)
    public function export(Request $request) {
        $query = ActivityLog::with('user');
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        $logs = $query->latest()->get();
        $csv = "ID,User ID,User Name,User Email,Action,Model,Model ID,IP Address,Status,Description,Created At\n";
        foreach ($logs as $log) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $log->id,
                $log->user_id,
                $log->user->name ?? 'N/A',
                $log->user->email ?? 'N/A',
                $log->action,
                $log->model,
                $log->model_id,
                $log->ip_address,
                $log->status,
                str_replace('"', '""', $log->description),
                $log->created_at
            );
        }
        return response($csv, 200)->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="activity_logs_' . now()->format('Y-m-d') . '.csv"');
    }

    // Cleanup activity logs lebih lama dari X hari
    public function cleanup(Request $request) {
        $data = $request->validate([
            'days' => 'required|integer|min:1',
        ], [
            'days.required' => 'Jumlah hari wajib diisi',
            'days.integer' => 'Jumlah hari harus berupa angka',
            'days.min' => 'Jumlah hari minimal 1',
        ]);
        $deleted = ActivityLog::where('created_at', '<', now()->subDays($data['days']))->delete();
        return $this->responseSuccess([
            'deleted_records' => $deleted,
            'kept_days' => $data['days'],
        ], 'Activity logs berhasil dihapus');
    }
}
