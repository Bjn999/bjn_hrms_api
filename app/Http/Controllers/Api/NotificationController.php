<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Notifications\TestBroadcastNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationController extends Controller
{
    /**
     * Get user's notifications with localized messages and unread count.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => __('messages.unauthorized')], 401);
        }

        $perPage = min((int)$request->get('per_page', 20), 50);
        $paginated = $user->notifications()->latest()->paginate($perPage);

        $unreadCount = $user->unreadNotifications()->count();

        $formattedList = collect($paginated->items())->map(function ($item) {
            $data = is_array($item->data) ? $item->data : (json_decode($item->data, true) ?: []);
            
            $titleKey = $data['title_key'] ?? null;
            $messageKey = $data['message_key'] ?? null;
            $titleParams = $data['title_params'] ?? [];
            $messageParams = $data['message_params'] ?? [];

            $title = $titleKey ? __("messages.{$titleKey}", $titleParams) : ($data['title'] ?? '');
            $message = $messageKey ? __("messages.{$messageKey}", $messageParams) : ($data['message'] ?? '');

            return [
                'id' => $item->id,
                'type' => $data['type'] ?? 'general',
                'title' => $title,
                'message' => $message,
                'action_url' => $data['action_url'] ?? null,
                'data' => $data,
                'read_at' => $item->read_at ? $item->read_at->toISOString() : null,
                'is_read' => !is_null($item->read_at),
                'created_at' => $item->created_at ? $item->created_at->toISOString() : null,
                'created_at_human' => $item->created_at ? $item->created_at->diffForHumans() : '',
            ];
        });

        return response()->json([
            'status' => true,
            'data' => [
                'notifications' => $formattedList,
                'unread_count' => $unreadCount,
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'has_more' => $paginated->hasMorePages(),
                ]
            ]
        ]);
    }

    /**
     * Get quick count of unread notifications.
     */
    public function unreadCount()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => __('messages.unauthorized')], 401);
        }

        return response()->json([
            'status' => true,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => __('messages.unauthorized')], 401);
        }

        $notification = $user->notifications()->find($id);
        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => __('messages.notification_not_found')
            ], 404);
        }

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json([
            'status' => true,
            'message' => __('messages.notification_marked_as_read'),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark all unread notifications as read for current user.
     */
    public function markAllAsRead()
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => __('messages.unauthorized')], 401);
        }

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'status' => true,
            'message' => __('messages.all_notifications_marked_as_read'),
            'unread_count' => 0,
        ]);
    }

    /**
     * Delete a notification.
     */
    public function destroy(string $id)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => __('messages.unauthorized')], 401);
        }

        $notification = $user->notifications()->find($id);
        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => __('messages.notification_not_found')
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'status' => true,
            'message' => __('messages.notification_deleted'),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Send a live test notification to the current user (via WebSockets + Database).
     */
    public function testBroadcast(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => __('messages.unauthorized')], 401);
        }

        $title = $request->input('title');
        $message = $request->input('message');
        $actionUrl = $request->input('action_url', '/portal');

        try {
            $user->notifyNow(new TestBroadcastNotification($title, $message, $actionUrl));

            return response()->json([
                'status' => true,
                'message' => __('messages.test_notification_sent'),
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        } catch (Throwable $e) {
            Log::error('Broadcast notification error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Notification broadcast failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
