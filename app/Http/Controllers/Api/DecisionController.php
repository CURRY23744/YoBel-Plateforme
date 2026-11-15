<?php

namespace App\Http\Controllers\Api;

use App\Enums\DecisionStatus;
use App\Models\Decision;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DecisionController extends ApiController
{
    public function index(Request $request, ?Project $project = null)
    {
        if ($project) {
            $this->authorizeProject($project);
            $query = Decision::where('project_id', $project->id);
        } else {
            $this->authorizeGeneral();
            $query = Decision::whereNull('project_id');
        }

        return response()->json(
            $query
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', '%'.$request->query('q').'%')->orWhere('decision', 'like', '%'.$request->query('q').'%')))
                ->with(['creator:id,first_name,last_name', 'participants:id,first_name,last_name'])
                ->orderByDesc('decided_at')->orderByDesc('id')
                ->paginate($this->perPage($request))
        );
    }

    public function store(Request $request, ?Project $project = null): JsonResponse
    {
        $me = $this->me();

        if ($project) {
            $this->authorizeProject($project, 'edit');
        } else {
            abort_unless($me->isStaff(), 403, 'Les décisions générales sont réservées aux gestionnaires.');
        }

        $data = $request->validate($this->rules($project));
        $participants = Arr::pull($data, 'participants');

        $decision = DB::transaction(function () use ($data, $participants, $project, $me) {
            $decision = Decision::create($data + [
                'project_id' => $project?->id,
                'organization_id' => $me->organization_id,
                'created_by' => $me->id,
            ]);

            if (is_array($participants)) {
                $decision->participants()->sync($participants);
            }

            // Une décision qui en remplace une autre la marque comme "remplacée".
            if ($decision->supersedes_id) {
                Decision::whereKey($decision->supersedes_id)->update(['status' => DecisionStatus::Superseded->value]);
            }

            return $decision;
        });

        return response()->json($decision->load(['creator:id,first_name,last_name', 'participants:id,first_name,last_name']), 201);
    }

    public function show(Decision $decision): JsonResponse
    {
        $this->authorizeDecision($decision);

        return response()->json($decision->load(['creator:id,first_name,last_name', 'participants:id,first_name,last_name', 'supersedes:id,title']));
    }

    public function update(Request $request, Decision $decision): JsonResponse
    {
        $this->authorizeDecision($decision, 'edit');

        $data = $request->validate($this->rules($decision->project, true));
        $participants = Arr::pull($data, 'participants');

        $decision->update($data);

        if (is_array($participants)) {
            $decision->participants()->sync($participants);
        }

        return response()->json($decision->fresh(['participants:id,first_name,last_name']));
    }

    public function destroy(Decision $decision): JsonResponse
    {
        $this->authorizeDecision($decision, 'delete');

        $decision->delete();

        return response()->json(null, 204);
    }

    private function authorizeDecision(Decision $decision, string $ability = 'view'): void
    {
        if ($decision->project_id) {
            abort_unless($decision->project, 404);
            $this->authorizeProject($decision->project, $ability);
        } else {
            $this->authorizeGeneral();
            abort_unless($ability === 'view' || $this->me()->isStaff(), 403);
        }
    }

    private function rules(?Project $project, bool $partial = false): array
    {
        $org = $this->me()->organization_id;
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => "$required|string|max:255",
            'decision' => "$required|string",
            'justification' => 'nullable|string',
            'status' => ['sometimes', Rule::enum(DecisionStatus::class)],
            'decided_at' => 'nullable|date',
            'discussion_id' => ['nullable', Rule::exists('discussions', 'id')->where('organization_id', $org)],
            'supersedes_id' => ['nullable', Rule::exists('decisions', 'id')->where('organization_id', $org)],
            'participants' => 'nullable|array',
            'participants.*' => [Rule::exists('users', 'id')->where('organization_id', $org)],
        ];
    }
}
