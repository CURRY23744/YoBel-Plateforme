<?php

namespace App\Http\Controllers\Api;

use App\Models\MeetingMinute;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeetingMinuteController extends ApiController
{
    public function index(Request $request, ?Project $project = null)
    {
        if ($project) {
            $this->authorizeProject($project);
            $query = MeetingMinute::where('project_id', $project->id);
        } elseif ($this->me()->isGuest()) {
            $query = MeetingMinute::whereIn('project_id', Project::visibleTo($this->me())->select('projects.id'));
        } else {
            $this->authorizeGeneral();
            $query = MeetingMinute::whereNull('project_id');
        }

        return response()->json(
            $query->with('creator:id,first_name,last_name')
                ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->query('q').'%'))
                ->orderByDesc('meeting_date')->orderByDesc('id')
                ->paginate($this->perPage($request))
        );
    }

    public function store(Request $request, ?Project $project = null): JsonResponse
    {
        $me = $this->me();
        $project ? $this->authorizeProject($project, 'edit') : abort_unless($me->isStaff(), 403);

        $data = $request->validate($this->rules());

        $minute = MeetingMinute::create($data + [
            'project_id' => $project?->id,
            'organization_id' => $me->organization_id,
            'created_by' => $me->id,
        ]);

        return response()->json($minute, 201);
    }

    public function show(MeetingMinute $minute): JsonResponse
    {
        $this->authorizeMinute($minute);

        return response()->json($minute->load('creator:id,first_name,last_name'));
    }

    public function update(Request $request, MeetingMinute $minute): JsonResponse
    {
        $this->authorizeMinute($minute, 'edit');

        $minute->update($request->validate($this->rules(true)));

        return response()->json($minute->fresh());
    }

    public function destroy(MeetingMinute $minute): JsonResponse
    {
        $this->authorizeMinute($minute, 'delete');

        $minute->delete();

        return response()->json(null, 204);
    }

    private function authorizeMinute(MeetingMinute $minute, string $ability = 'view'): void
    {
        if ($minute->project_id) {
            abort_unless($minute->project, 404);
            $this->authorizeProject($minute->project, $ability);
        } else {
            $this->authorizeGeneral();
            abort_unless($ability === 'view' || $this->me()->isStaff(), 403);
        }
    }

    private function rules(bool $partial = false): array
    {
        $org = $this->me()->organization_id;
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => "$required|string|max:255",
            'content' => "$required|string",
            'meeting_date' => 'nullable|date',
            'event_id' => ['nullable', Rule::exists('events', 'id')->where('organization_id', $org)],
            'attendees' => 'nullable|array',
            'attendees.*' => [Rule::exists('users', 'id')->where('organization_id', $org)],
        ];
    }
}
