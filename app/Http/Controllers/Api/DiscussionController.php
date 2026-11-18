<?php

namespace App\Http\Controllers\Api;

use App\Models\Discussion;
use App\Models\Message;
use App\Models\Project;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Discussions d'un projet (/projects/{project}/discussions) ou de l'espace général (/discussions).
 */
class DiscussionController extends ApiController
{
    public function index(Request $request, ?Project $project = null)
    {
        if ($project) {
            $this->authorizeProject($project);
            $query = Discussion::where('project_id', $project->id);
        } else {
            $this->authorizeGeneral();
            $query = Discussion::whereNull('project_id');
        }

        $discussions = $query
            ->with('creator:id,first_name,last_name')
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->query('q').'%'))
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return response()->json($discussions);
    }

    public function store(Request $request, ?Project $project = null): JsonResponse
    {
        $me = $this->me();

        if ($project) {
            $this->authorizeProject($project, 'comment');
        } else {
            $this->authorizeGeneral();
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string|max:10000', // premier message (optionnel)
            'is_pinned' => 'nullable|boolean',
        ]);

        $discussion = DB::transaction(function () use ($data, $project, $me) {
            $discussion = Discussion::create([
                'project_id' => $project?->id,
                'organization_id' => $me->organization_id,
                'title' => $data['title'],
                'is_pinned' => $me->isStaff() ? ($data['is_pinned'] ?? false) : false,
                'created_by' => $me->id,
            ]);

            if (! empty($data['body'])) {
                Message::create(['discussion_id' => $discussion->id, 'user_id' => $me->id, 'body' => $data['body']]);
                $discussion->update(['messages_count' => 1, 'last_message_at' => now()]);
            }

            return $discussion;
        });

        $discussion->load('creator:id,first_name,last_name');

        if (! empty($data['body'])) {
            $message = $discussion->messages()->latest('id')->firstOrFail();
            Notifier::discussionMessage($discussion, $me, $message);
        }

        return response()->json($discussion, 201);
    }

    public function show(Discussion $discussion): JsonResponse
    {
        $this->authorizeDiscussion($discussion);

        $discussion->load('creator:id,first_name,last_name');

        return response()->json($discussion);
    }

    public function update(Request $request, Discussion $discussion): JsonResponse
    {
        $this->authorizeDiscussion($discussion);
        abort_unless($this->canManageScope($discussion->project, $discussion->created_by), 403);

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'is_pinned' => 'sometimes|boolean',
            'is_locked' => 'sometimes|boolean',
        ]);

        // Épingler / verrouiller : réservé aux gestionnaires (pas à un simple auteur)
        if ((isset($data['is_pinned']) || isset($data['is_locked'])) && ! $this->canManageScope($discussion->project)) {
            abort(403, 'Épingler ou verrouiller une discussion est réservé aux gestionnaires.');
        }

        $discussion->update($data);

        return response()->json($discussion->fresh());
    }

    public function destroy(Discussion $discussion): JsonResponse
    {
        $this->authorizeDiscussion($discussion);
        abort_unless($this->canManageScope($discussion->project, $discussion->created_by), 403);

        $discussion->delete();

        return response()->json(null, 204);
    }

}
