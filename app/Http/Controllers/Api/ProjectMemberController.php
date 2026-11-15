<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMemberController extends ApiController
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $members = $project->members()
            ->with('user:id,first_name,last_name,email,job_title,avatar_path,role')
            ->when(! $request->boolean('include_former'), fn ($q) => $q->whereNull('left_at'))
            ->orderBy('joined_at')
            ->get();

        return response()->json($members);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project, 'manage');

        $data = $request->validate($this->rules());
        $user = User::where('organization_id', $project->organization_id)->where('is_active', true)->findOrFail($data['user_id']);

        $member = ProjectMember::firstOrNew(['project_id' => $project->id, 'user_id' => $user->id]);
        $wasActive = $member->exists && $member->left_at === null;

        $member->fill($this->flags($data) + [
            'project_role' => $data['project_role'] ?? $member->project_role ?? 'developer',
            'allocation_percent' => $data['allocation_percent'] ?? $member->allocation_percent,
            'notes' => $data['notes'] ?? $member->notes,
            'left_at' => null,
            'joined_at' => $wasActive ? $member->joined_at : now(),
            'added_by' => $this->me()->id,
        ])->save();

        if (! $wasActive) {
            Notifier::send($user, 'project.member_added', "Vous avez été ajouté au projet {$project->name}", null, ['project_id' => $project->id]);
        }

        return response()->json($member->load('user:id,first_name,last_name,email'), $wasActive ? 200 : 201);
    }

    public function update(Request $request, Project $project, User $user): JsonResponse
    {
        $this->authorizeProject($project, 'manage');

        $member = $this->activeMember($project, $user);
        $data = $request->validate($this->rules(true));

        $member->update($this->flags($data) + array_filter([
            'project_role' => $data['project_role'] ?? null,
            'allocation_percent' => $data['allocation_percent'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], fn ($v) => $v !== null));

        return response()->json($member->fresh('user:id,first_name,last_name,email'));
    }

    /** Retire un membre : il reste dans l'historique du projet (left_at) mais perd tout accès. */
    public function destroy(Project $project, User $user): JsonResponse
    {
        $this->authorizeProject($project, 'manage');

        $member = $this->activeMember($project, $user);
        $member->update(ProjectMember::NO_RIGHTS + ['left_at' => now()]);

        return response()->json(['message' => 'Membre retiré du projet.']);
    }

    private function activeMember(Project $project, User $user): ProjectMember
    {
        return ProjectMember::where('project_id', $project->id)->where('user_id', $user->id)->whereNull('left_at')->firstOrFail();
    }

    /** Droits finaux : jeu prédéfini, puis surcharges explicites. */
    private function flags(array $data): array
    {
        $flags = isset($data['preset']) ? ProjectMember::PRESETS[$data['preset']] : [];

        foreach (['can_view', 'can_comment', 'can_upload', 'can_edit', 'can_delete', 'can_manage'] as $key) {
            if (array_key_exists($key, $data)) {
                $flags[$key] = (bool) $data[$key];
            }
        }

        // can_view est obligatoire pour tout autre droit ; par défaut un nouveau membre peut voir.
        if (! isset($flags['can_view'])) {
            $flags['can_view'] = true;
        }
        if (! $flags['can_view']) {
            $flags = ProjectMember::NO_RIGHTS;
        }

        return $flags;
    }

    private function rules(bool $partial = false): array
    {
        return [
            'user_id' => $partial ? 'sometimes' : 'required|integer',
            'project_role' => ['nullable', Rule::in(config('platform.project_member_roles'))],
            'preset' => ['nullable', Rule::in(array_keys(ProjectMember::PRESETS))],
            'can_view' => 'nullable|boolean',
            'can_comment' => 'nullable|boolean',
            'can_upload' => 'nullable|boolean',
            'can_edit' => 'nullable|boolean',
            'can_delete' => 'nullable|boolean',
            'can_manage' => 'nullable|boolean',
            'allocation_percent' => 'nullable|integer|between:1,100',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
