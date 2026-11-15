<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Models\ProjectType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectTypeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $me = $this->me();

        $types = ProjectType::query()
            ->withCount(['projects' => fn ($q) => $q->visibleTo($me)])
            ->when(! $me->isAdmin() || ! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return response()->json($types);
    }

    public function show(ProjectType $projectType): JsonResponse
    {
        return response()->json($projectType->loadCount(['projects' => fn ($q) => $q->visibleTo($this->me())]));
    }

    public function store(Request $request): JsonResponse
    {
        $orgId = $this->me()->organization_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('project_types')->where('organization_id', $orgId)],
            'description' => 'nullable|string|max:1000',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['slug'] = $this->uniqueSlug($data['name'], $orgId);

        return response()->json(ProjectType::create($data), 201);
    }

    public function update(Request $request, ProjectType $projectType): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('project_types')->where('organization_id', $projectType->organization_id)->ignore($projectType->id)],
            'description' => 'nullable|string|max:1000',
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $projectType->update($data);

        return response()->json($projectType->fresh());
    }

    public function destroy(ProjectType $projectType): JsonResponse
    {
        // On compte aussi les projets dont l'utilisateur n'a pas la visibilité.
        if (Project::withTrashed()->where('project_type_id', $projectType->id)->exists()) {
            return response()->json(['message' => 'Ce type est utilisé par des projets. Désactivez-le plutôt que de le supprimer.'], 409);
        }

        $projectType->delete();

        return response()->json(null, 204);
    }

    private function uniqueSlug(string $name, int $orgId): string
    {
        $base = Str::slug($name) ?: 'type';
        $slug = $base;
        $i = 2;

        while (ProjectType::where('organization_id', $orgId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
