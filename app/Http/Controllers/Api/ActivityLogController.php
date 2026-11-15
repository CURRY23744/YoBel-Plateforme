<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends ApiController
{
    /** Journal global (administrateur). ?export=csv pour l'export. */
    public function index(Request $request)
    {
        $query = $this->filtered(ActivityLog::query(), $request)->with(['user:id,first_name,last_name', 'project:id,reference,name']);

        if ($request->query('export') === 'csv') {
            return $this->csv($query);
        }

        return response()->json($query->latest('created_at')->latest('id')->paginate($this->perPage($request)));
    }

    /** Historique d'un projet (tout membre ayant accès au projet). */
    public function project(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        $logs = ActivityLog::where('project_id', $project->id)
            ->with('user:id,first_name,last_name')
            ->latest('created_at')->latest('id')
            ->paginate($this->perPage($request));

        return response()->json($logs);
    }

    private function filtered($query, Request $request)
    {
        return $query
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->query('user_id')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('subject_type'), fn ($q) => $q->where('subject_type', 'like', '%'.$request->query('subject_type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')));
    }

    private function csv($query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'utilisateur', 'projet', 'action', 'élément', 'description', 'ip']);

            $query->latest('created_at')->chunk(500, function ($logs) use ($out) {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at,
                        $log->user?->name,
                        $log->project?->reference,
                        $log->action,
                        class_basename((string) $log->subject_type).'#'.$log->subject_id,
                        $log->description,
                        $log->ip_address,
                    ]);
                }
            });

            fclose($out);
        }, 'journal-activite.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
