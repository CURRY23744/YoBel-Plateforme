<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->me();

        $notifications = $user->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = $this->me()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json($notification->fresh());
    }

    public function markAllRead(): JsonResponse
    {
        $this->me()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications sont lues.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->me()->notifications()->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
