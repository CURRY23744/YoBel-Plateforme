<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends ApiController
{
    public function index(Request $request)
    {
        $this->authorizeGeneral();
        $me = $this->me();

        $query = Announcement::query()->with('creator:id,first_name,last_name');

        if (! ($me->isStaff() && $request->boolean('all'))) {
            $query->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        }

        return response()->json($query->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id')->paginate($this->perPage($request)));
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorizeGeneral();

        return response()->json($announcement->load('creator:id,first_name,last_name'));
    }

    public function store(Request $request): JsonResponse
    {
        $me = $this->me();
        $data = $request->validate($this->rules());

        $announcement = Announcement::create($data + [
            'organization_id' => $me->organization_id,
            'created_by' => $me->id,
            'published_at' => $data['published_at'] ?? now(),
        ]);

        if (! $announcement->published_at->isFuture()) {
            $recipients = User::where('organization_id', $me->organization_id)->where('is_active', true)->where('role', '!=', 'guest')->get();
            Notifier::send($recipients, 'announcement', $announcement->title, null, ['announcement_id' => $announcement->id]);
        }

        return response()->json($announcement, 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $announcement->update($request->validate($this->rules(true)));

        return response()->json($announcement->fresh());
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(null, 204);
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => "$required|string|max:255",
            'body' => "$required|string",
            'is_pinned' => 'nullable|boolean',
            'published_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:published_at',
        ];
    }
}
