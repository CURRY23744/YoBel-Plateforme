<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->me();

        $query = $this->notificationsFor($user);
        $notifications = (clone $query)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json([
            'unread_count' => (clone $query)->whereNull('read_at')->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = $this->notificationsFor($this->me())->findOrFail($id);
        $notification->markAsRead();

        return response()->json($notification->fresh());
    }

    public function markAllRead(): JsonResponse
    {
        $this->notificationsFor($this->me())->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications sont lues.']);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->notificationsFor($this->me())->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    private function notificationsFor($user)
    {
        $query = $user->notifications();
        if (! $user->isGuest()) {
            return $query;
        }

        $projectIds = Project::visibleTo($user)->pluck('projects.id');
        if ($projectIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($notifications) use ($projectIds) {
            foreach ($projectIds as $projectId) {
                $notifications->orWhereJsonContains('data->project_id', (int) $projectId);
            }
        });
    }
}
