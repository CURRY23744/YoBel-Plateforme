<?php

namespace App\Http\Controllers\Api;

use App\Models\Decision;
use App\Models\Discussion;
use App\Models\Folder;
use App\Models\Message;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Recherche globale : ne renvoie jamais un élément auquel l'utilisateur n'a pas accès. */
class SearchController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);
        $me = $this->me();
        $term = '%'.$request->query('q').'%';
        $limit = 10;

        $projects = Project::visibleTo($me)->get(['id', 'organization_id']);
        $projectIds = $projects->pluck('id');

        // Fichiers : on écarte les dossiers restreints
        $deniedFolders = $projects->flatMap(fn ($p) => Folder::deniedIdsFor($p, $me))->all();

        $results = [
            'projects' => Project::visibleTo($me)
                ->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('reference', 'like', $term)->orWhere('description', 'like', $term))
                ->limit($limit)->get(['id', 'reference', 'name', 'status']),

            'files' => ProjectFile::whereIn('project_id', $projectIds)->where('is_current', true)
                ->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('original_name', 'like', $term))
                ->when($deniedFolders, fn ($q) => $q->where(fn ($w) => $w->whereNull('folder_id')->orWhereNotIn('folder_id', $deniedFolders)))
                ->limit($limit)->get(['id', 'project_id', 'name', 'category']),

            'tasks' => Task::whereIn('project_id', $projectIds)->where('title', 'like', $term)->limit($limit)->get(['id', 'project_id', 'title', 'status']),

            'decisions' => Decision::whereIn('project_id', $projectIds)
                ->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('decision', 'like', $term))
                ->limit($limit)->get(['id', 'project_id', 'title', 'status']),
        ];

        $discussionScope = fn ($q) => $q->where(function ($w) use ($projectIds, $me) {
            $w->whereIn('project_id', $projectIds);
            if (! $me->isGuest()) {
                $w->orWhereNull('project_id');
            }
        });

        $results['discussions'] = Discussion::query()->tap($discussionScope)->where('title', 'like', $term)->limit($limit)->get(['id', 'project_id', 'title']);

        $results['messages'] = Message::where('body', 'like', $term)
            ->whereHas('discussion', fn ($d) => $d->tap($discussionScope))
            ->with('discussion:id,project_id,title')
            ->limit($limit)->get(['id', 'discussion_id', 'body', 'created_at']);

        return response()->json($results);
    }
}
