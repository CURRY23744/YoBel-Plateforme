<?php

namespace App\Http\Controllers\Api;

use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FolderController extends ApiController
{
    /** Liste à plat (parent_id) des dossiers visibles ; l'interface reconstruit l'arborescence. */
    public function index(Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        $me = $this->me();

        $denied = Folder::deniedIdsFor($project, $me);

        $folders = Folder::where('project_id', $project->id)
            ->when($denied, fn ($q) => $q->whereNotIn('id', $denied))
            ->withCount('files')
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $grants = DB::table('folder_permissions')
            ->where('user_id', $me->id)
            ->whereIn('folder_id', $folders->pluck('id'))
            ->get()
            ->keyBy('folder_id');
        $projectPermissions = $me->abilitiesOn($project);
        $managesProject = $me->isAdmin() || $projectPermissions['manage'];

        foreach ($folders as $folder) {
            $grant = $grants->get($folder->id);
            $folder->setAttribute('my_permissions', $managesProject
                ? ['view' => true, 'upload' => true, 'edit' => true, 'delete' => true]
                : [
                    'view' => true,
                    'upload' => $projectPermissions['upload'] && (! $folder->is_restricted || (bool) ($grant?->can_upload)),
                    'edit' => $projectPermissions['edit'] && (! $folder->is_restricted || (bool) ($grant?->can_edit)),
                    'delete' => $projectPermissions['delete'] && (! $folder->is_restricted || (bool) ($grant?->can_delete)),
                ]);
        }

        return response()->json($folders);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project, 'upload');
        $me = $this->me();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => ['nullable', Rule::exists('folders', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
            'is_restricted' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (! empty($data['is_restricted'])) {
            abort_unless($me->canOnProject($project, 'manage'), 403, 'Seul un gestionnaire peut restreindre un dossier.');
        }

        if (! empty($data['parent_id'])) {
            abort_unless(Folder::findOrFail($data['parent_id'])->accessibleBy($me, 'upload'), 403, 'Dossier parent non autorisé.');
        }

        $folder = Folder::create($data + ['project_id' => $project->id, 'created_by' => $me->id]);

        return response()->json($folder, 201);
    }

    public function show(Folder $folder): JsonResponse
    {
        $this->authorizeProject($folder->project);
        abort_unless($folder->accessibleBy($this->me()), 404);

        return response()->json($folder->loadCount('files')->load('permissions:id,first_name,last_name'));
    }

    public function update(Request $request, Folder $folder): JsonResponse
    {
        $project = $folder->project;
        $this->authorizeProject($project, 'edit');
        abort_unless($folder->accessibleBy($this->me(), 'edit'), 403);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => ['nullable', Rule::exists('folders', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
            'is_restricted' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (array_key_exists('is_restricted', $data)) {
            abort_unless($this->me()->canOnProject($project, 'manage'), 403, 'Seul un gestionnaire peut modifier la restriction.');
        }

        if (! empty($data['parent_id']) && (int) $data['parent_id'] === $folder->id) {
            return response()->json(['message' => 'Un dossier ne peut pas être son propre parent.'], 422);
        }

        $folder->update($data);

        return response()->json($folder->fresh());
    }

    public function destroy(Folder $folder): JsonResponse
    {
        $this->authorizeProject($folder->project, 'delete');
        abort_unless($folder->accessibleBy($this->me(), 'delete'), 403);

        if ($folder->children()->exists() || $folder->files()->exists()) {
            return response()->json(['message' => 'Le dossier n\'est pas vide. Déplacez ou supprimez son contenu d\'abord.'], 409);
        }

        $folder->delete();

        return response()->json(null, 204);
    }

    /** Définit les droits d'accès à un dossier : PUT { is_restricted, permissions: [{user_id, can_*}] } */
    public function permissions(Request $request, Folder $folder): JsonResponse
    {
        $project = $folder->project;
        $this->authorizeProject($project, 'manage');

        $data = $request->validate([
            'is_restricted' => 'required|boolean',
            'permissions' => 'nullable|array',
            'permissions.*.user_id' => 'required|integer|distinct',
            'permissions.*.can_view' => 'nullable|boolean',
            'permissions.*.can_upload' => 'nullable|boolean',
            'permissions.*.can_edit' => 'nullable|boolean',
            'permissions.*.can_delete' => 'nullable|boolean',
        ]);

        $permissions = collect($data['permissions'] ?? []);

        $memberIds = ProjectMember::where('project_id', $project->id)->whereNull('left_at')->pluck('user_id');
        $unknown = $permissions->pluck('user_id')->diff($memberIds);

        if ($unknown->isNotEmpty()) {
            return response()->json([
                'message' => 'Seuls les membres actifs du projet peuvent recevoir un accès.',
                'errors' => ['permissions' => ['Utilisateurs non membres : '.$unknown->implode(', ')]],
            ], 422);
        }

        $folder->update(['is_restricted' => $data['is_restricted']]);

        $folder->permissions()->sync(
            $permissions->mapWithKeys(fn ($p) => [$p['user_id'] => [
                'can_view' => $p['can_view'] ?? true,
                'can_upload' => $p['can_upload'] ?? false,
                'can_edit' => $p['can_edit'] ?? false,
                'can_delete' => $p['can_delete'] ?? false,
                'granted_by' => $this->me()->id,
            ]])->all()
        );

        return response()->json($folder->fresh()->load('permissions:id,first_name,last_name'));
    }
}
