<?php

namespace App\Http\Controllers\Api;

use App\Enums\FileCategory;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectFileController extends ApiController
{
    public function index(Request $request, Project $project)
    {
        $this->authorizeProject($project);
        $me = $this->me();

        $denied = Folder::deniedIdsFor($project, $me);

        $files = ProjectFile::where('project_id', $project->id)
            ->when(! $request->boolean('all_versions'), fn ($q) => $q->where('is_current', true))
            ->when($denied, fn ($q) => $q->where(fn ($w) => $w->whereNull('folder_id')->orWhereNotIn('folder_id', $denied)))
            ->when($request->filled('folder_id'), fn ($q) => $request->query('folder_id') === 'root'
                ? $q->whereNull('folder_id')
                : $q->where('folder_id', (int) $request->query('folder_id')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->query('category')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = '%'.$request->query('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('original_name', 'like', $s)->orWhere('description', 'like', $s));
            })
            ->with('uploader:id,first_name,last_name')
            ->latest()
            ->paginate($this->perPage($request));

        return response()->json($files);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project, 'upload');
        $me = $this->me();

        $data = $request->validate([
            'file' => $this->fileRules(),
            'name' => 'nullable|string|max:255',
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('project_id', $project->id)->whereNull('deleted_at')],
            'category' => ['nullable', Rule::enum(FileCategory::class)],
            'description' => 'nullable|string|max:2000',
        ]);

        if (! empty($data['folder_id'])) {
            abort_unless(Folder::findOrFail($data['folder_id'])->accessibleBy($me, 'upload'), 403, 'Vous ne pouvez pas déposer dans ce dossier.');
        }

        $file = $this->persist($request->file('file'), $project, [
            'folder_id' => $data['folder_id'] ?? null,
            'name' => $data['name'] ?? $request->file('file')->getClientOriginalName(),
            'category' => $data['category'] ?? 'other',
            'description' => $data['description'] ?? null,
        ]);

        return response()->json($file->load('uploader:id,first_name,last_name'), 201);
    }

    public function show(ProjectFile $file): JsonResponse
    {
        $this->authorizeFile($file, 'view');

        return response()->json($file->load(['uploader:id,first_name,last_name', 'folder:id,name']));
    }

    public function update(Request $request, ProjectFile $file): JsonResponse
    {
        $this->authorizeFile($file, 'edit');

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => ['sometimes', Rule::enum(FileCategory::class)],
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('project_id', $file->project_id)->whereNull('deleted_at')],
        ]);

        if (! empty($data['folder_id'])) {
            abort_unless(Folder::findOrFail($data['folder_id'])->accessibleBy($this->me(), 'upload'), 403, 'Dossier de destination non autorisé.');
        }

        $file->update($data);

        return response()->json($file->fresh());
    }

    public function destroy(ProjectFile $file): JsonResponse
    {
        $this->authorizeFile($file, 'delete');

        $file->delete(); // suppression logique : le fichier physique est conservé

        return response()->json(null, 204);
    }

    public function download(ProjectFile $file)
    {
        $this->authorizeFile($file, 'view');

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404, 'Fichier physique introuvable.');

        $file->increment('downloads_count');

        return $disk->download($file->path, $file->original_name);
    }

    /** Aperçu direct (images et PDF) sans téléchargement. */
    public function preview(ProjectFile $file)
    {
        $this->authorizeFile($file, 'view');

        $previewable = Str::startsWith((string) $file->mime_type, 'image/') || $file->mime_type === 'application/pdf';
        abort_unless($previewable, 415, 'Aperçu non disponible pour ce type de fichier.');

        $disk = Storage::disk($file->disk);
        abort_unless($disk->exists($file->path), 404, 'Fichier physique introuvable.');

        return $disk->response($file->path, $file->original_name, ['Content-Type' => $file->mime_type]);
    }

    public function versions(ProjectFile $file): JsonResponse
    {
        $this->authorizeFile($file, 'view');

        $rootId = $file->root_file_id ?? $file->id;

        $versions = ProjectFile::where(fn ($q) => $q->where('id', $rootId)->orWhere('root_file_id', $rootId))
            ->with('uploader:id,first_name,last_name')
            ->orderByDesc('version_number')
            ->get();

        return response()->json($versions);
    }

    public function newVersion(Request $request, ProjectFile $file): JsonResponse
    {
        $this->authorizeFile($file, 'upload');

        $request->validate(['file' => $this->fileRules(), 'description' => 'nullable|string|max:2000']);

        $rootId = $file->root_file_id ?? $file->id;
        $last = ProjectFile::where(fn ($q) => $q->where('id', $rootId)->orWhere('root_file_id', $rootId))->max('version_number');

        $new = $this->persist($request->file('file'), $file->project, [
            'folder_id' => $file->folder_id,
            'name' => $file->name,
            'category' => $file->category,
            'description' => $request->input('description', $file->description),
            'root_file_id' => $rootId,
            'version_number' => $last + 1,
        ]);

        ProjectFile::where(fn ($q) => $q->where('id', $rootId)->orWhere('root_file_id', $rootId))
            ->where('id', '!=', $new->id)
            ->update(['is_current' => false]);

        Notifier::send(
            $file->project->members()->whereNull('left_at')->with('user')->get()->pluck('user'),
            'file.new_version',
            "Nouvelle version de « {$file->name} »",
            null,
            ['project_id' => $file->project_id, 'file_id' => $new->id]
        );

        return response()->json($new->load('uploader:id,first_name,last_name'), 201);
    }

    // ----- Helpers -----

    private function authorizeFile(ProjectFile $file, string $ability): void
    {
        abort_unless($file->project, 404);
        $this->authorizeProject($file->project, $ability);
        abort_unless($file->folderAllows($this->me(), $ability === 'view' ? 'view' : $ability), $ability === 'view' ? 404 : 403);
    }

    private function fileRules(): array
    {
        return ['required', 'file', 'mimes:'.implode(',', config('platform.allowed_extensions')), 'max:'.config('platform.upload_max_kb')];
    }

    private function persist(UploadedFile $upload, Project $project, array $attributes): ProjectFile
    {
        $disk = config('platform.storage_disk');
        $extension = strtolower($upload->getClientOriginalExtension());
        $checksum = hash_file('sha256', $upload->getRealPath());
        $mime = $upload->getMimeType() ?: $upload->getClientMimeType();
        $size = $upload->getSize();

        $path = $upload->storeAs(
            "org-{$project->organization_id}/project-{$project->id}",
            Str::uuid().($extension ? ".{$extension}" : ''),
            $disk
        );

        return ProjectFile::create($attributes + [
            'project_id' => $project->id,
            'uploaded_by' => $this->me()->id,
            'original_name' => $upload->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mime,
            'extension' => $extension,
            'size' => $size,
            'checksum' => $checksum,
        ]);
    }
}
