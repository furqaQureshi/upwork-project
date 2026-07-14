<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->formatNotification($notification));

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $notifications,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->whereKey($notificationId)
            ->firstOrFail();

        if (! $notification->read_at) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
            'notification_id' => $notification->id,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'unread_count' => 0,
        ]);
    }

    private function formatNotification(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => data_get($notification->data, 'type', $notification->type),
            'title' => data_get($notification->data, 'title', 'Notification'),
            'body' => data_get($notification->data, 'body', ''),
            'url' => data_get($notification->data, 'url', route('chat.index')),
            'icon' => data_get($notification->data, 'icon', asset('icons/icon.svg')),
            'sound' => data_get($notification->data, 'sound', setting('notification_sound_url', '')),
            'is_read' => (bool) $notification->read_at,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_human' => $notification->created_at?->diffForHumans(),
        ];
    }
}
