<?php

namespace App\Http\Controllers\Api;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MilestoneController extends ApiController
{
    public function index(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json($project->milestones()->get());
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project, 'edit');

        $data = $this->validated($request);
        $milestone = $project->milestones()->create($data + ['created_by' => $this->me()->id]);

        return response()->json($milestone, 201);
    }

    public function update(Request $request, Milestone $milestone): JsonResponse
    {
        $this->authorizeProject($milestone->project, 'edit');

        $milestone->update($this->validated($request, true));

        return response()->json($milestone->fresh());
    }

    public function destroy(Milestone $milestone): JsonResponse
    {
        $this->authorizeProject($milestone->project, 'edit');

        $milestone->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $data = $request->validate([
            'title' => ($partial ? 'sometimes' : 'required').'|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => ['nullable', Rule::in(config('platform.milestone_statuses'))],
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now();
        } elseif (isset($data['status'])) {
            $data['completed_at'] = null;
        }

        return $data;
    }
}
