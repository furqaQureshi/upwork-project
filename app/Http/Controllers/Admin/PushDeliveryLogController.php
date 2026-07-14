<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushDeliveryLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PushDeliveryLogController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $userId = $request->integer('user_id');
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $errorCode = trim((string) $request->query('error_code', ''));

        $baseQuery = PushDeliveryLog::query()->where('provider', 'fcm');

        $logs = PushDeliveryLog::query()
            ->where('provider', 'fcm')
            ->with('user')
            ->when($status !== '', function ($builder) use ($status): void {
                $builder->where('status', $status);
            })
            ->when($userId > 0, function ($builder) use ($userId): void {
                $builder->where('user_id', $userId);
            })
            ->when($dateFrom !== '', function ($builder) use ($dateFrom): void {
                $builder->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($builder) use ($dateTo): void {
                $builder->whereDate('created_at', '<=', $dateTo);
            })
            ->when($errorCode !== '', function ($builder) use ($errorCode): void {
                $builder->where('error_code', 'like', '%'.$errorCode.'%');
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $userIds = PushDeliveryLog::query()
            ->where('provider', 'fcm')
            ->whereNotNull('user_id')
            ->latest('id')
            ->limit(500)
            ->pluck('user_id')
            ->unique()
            ->values();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'success' => (clone $baseQuery)->where('status', 'success')->count(),
            'failure' => (clone $baseQuery)->where('status', 'failure')->count(),
        ];

        return view('admin.push-delivery-logs.index', [
            'logs' => $logs,
            'users' => $users,
            'summary' => $summary,
        ]);
    }
}
