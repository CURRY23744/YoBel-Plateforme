<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $me = $this->me();
        $projectIds = Project::visibleTo($me)->pluck('id');

        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date']);

        $events = Event::query()
            ->where(function ($w) use ($me, $projectIds) {
                if ($me->isGuest()) {
                    $w->whereIn('project_id', $projectIds)
                        ->where(fn ($projectEvents) => $projectEvents
                            ->where('created_by', $me->id)
                            ->orWhereHas('participants', fn ($p) => $p->where('users.id', $me->id))
                            ->orWhereIn('visibility', ['project', 'organization']));

                    return;
                }

                $w->where('created_by', $me->id)
                    ->orWhereHas('participants', fn ($p) => $p->where('users.id', $me->id))
                    ->orWhere(fn ($g) => $g->whereIn('project_id', $projectIds)->whereIn('visibility', ['project', 'organization']))
                    ->orWhere(fn ($g) => $g->whereNull('project_id')->where('visibility', 'organization'));
            })
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when($request->boolean('mine'), fn ($q) => $q->where(fn ($w) => $w->where('created_by', $me->id)->orWhereHas('participants', fn ($p) => $p->where('users.id', $me->id))))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('to'), fn ($q) => $q->where('starts_at', '<=', $request->query('to').' 23:59:59'))
            ->when($request->filled('from'), fn ($q) => $q->where(fn ($w) => $w
                ->where('ends_at', '>=', $request->query('from'))
                ->orWhere(fn ($x) => $x->whereNull('ends_at')->where('starts_at', '>=', $request->query('from')))))
            ->with(['participants:id,first_name,last_name', 'project:id,organization_id,manager_id,reference,name'])
            ->orderBy('starts_at')
            ->limit(500)
            ->get();

        $manageableProjectIds = $me->isAdmin()
            ? $projectIds->all()
            : ProjectMember::where('user_id', $me->id)
                ->whereNull('left_at')
                ->where('can_manage', true)
                ->whereIn('project_id', $projectIds)
                ->pluck('project_id')
                ->map(fn ($id) => (int) $id)
                ->all();

        $events->each(function (Event $event) use ($manageableProjectIds, $me) {
            $canManage = $event->created_by === $me->id
                || $me->isAdmin()
                || ($event->project_id && in_array((int) $event->project_id, $manageableProjectIds, true));
            $event->setAttribute('can_manage', $canManage);
        });

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $me = $this->me();
        $data = $request->validate($this->rules());
        $participantIds = Arr::pull($data, 'participants', []) ?? [];

        $project = null;
        if (! empty($data['project_id'])) {
            $project = Project::findOrFail($data['project_id']);
            $this->authorizeProject($project, 'edit');
        } else {
            $this->authorizeGeneral();
            if (($data['visibility'] ?? 'private') === 'project') {
                return response()->json(['message' => 'La visibilité « project » nécessite un projet.', 'errors' => ['visibility' => ['Projet requis.']]], 422);
            }
        }

        $participants = $this->resolveParticipants($participantIds, $project);
        if ($participants === false) {
            return response()->json(['message' => 'Un participant n\'a pas accès à ce projet.', 'errors' => ['participants' => ['Participant non autorisé.']]], 422);
        }

        $data['visibility'] = $data['visibility'] ?? ($project ? 'project' : 'private');

        $event = DB::transaction(function () use ($data, $participants, $me) {
            $event = Event::create($data + ['organization_id' => $me->organization_id, 'created_by' => $me->id]);
            $event->participants()->sync($participants->mapWithKeys(fn ($u) => [$u->id => ['response_status' => 'pending', 'notified_at' => now()]])->all());

            return $event;
        });

        Notifier::send($participants, 'event.invited', "Invitation : {$event->title}", $event->starts_at->format('d/m/Y H:i'), ['project_id' => $event->project_id, 'event_id' => $event->id]);

        return response()->json($event->load('participants:id,first_name,last_name'), 201);
    }

    public function show(Event $event): JsonResponse
    {
        if ($this->me()->isGuest()) {
            abort_unless($event->project_id && $this->me()->canOnProject($event->project, 'view'), 404);
        }
        $this->authorizeEvent($event);

        return response()->json($event->load(['participants:id,first_name,last_name', 'project:id,reference,name', 'creator:id,first_name,last_name']));
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($event, true);

        $data = $request->validate($this->rules(true));
        unset($data['project_id']); // un événement ne change pas de projet
        $participantIds = Arr::pull($data, 'participants');

        $event->update($data);

        if (is_array($participantIds)) {
            $participants = $this->resolveParticipants($participantIds, $event->project);
            if ($participants === false) {
                return response()->json(['message' => 'Un participant n\'a pas accès à ce projet.'], 422);
            }

            $existing = $event->participants()->pluck('users.id')->all();
            $sync = $participants->mapWithKeys(fn ($u) => [$u->id => in_array($u->id, $existing, true) ? [] : ['response_status' => 'pending', 'notified_at' => now()]])->all();
            $event->participants()->sync($sync);

            Notifier::send($participants->reject(fn ($u) => in_array($u->id, $existing, true)), 'event.invited', "Invitation : {$event->title}", null, ['project_id' => $event->project_id, 'event_id' => $event->id]);
        }

        return response()->json($event->fresh('participants:id,first_name,last_name'));
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorizeEvent($event, true);

        $event->delete();

        return response()->json(null, 204);
    }

    public function respond(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEvent($event);
        $data = $request->validate(['response_status' => 'required|in:accepted,declined,tentative']);

        $updated = $event->participants()->updateExistingPivot($this->me()->id, [
            'response_status' => $data['response_status'],
            'responded_at' => now(),
        ]);

        abort_unless($updated, 403, 'Vous ne faites pas partie des invités.');

        return response()->json(['message' => 'Réponse enregistrée.', 'response_status' => $data['response_status']]);
    }

    // ----- Helpers -----

    private function authorizeEvent(Event $event, bool $write = false): void
    {
        $me = $this->me();
        if ($me->isGuest()) {
            abort_unless($event->project_id && $me->canOnProject($event->project, 'view'), 404);
        }
        $isCreator = $event->created_by === $me->id;
        $isParticipant = $event->participants()->where('users.id', $me->id)->exists();

        if ($write) {
            $allowed = $isCreator || $me->isAdmin() || ($event->project && $me->canOnProject($event->project, 'manage'));
            abort_unless($allowed, 403, 'Vous ne pouvez pas modifier cet événement.');

            return;
        }

        if ($isCreator || $isParticipant) {
            return;
        }

        $visible = match ($event->visibility) {
            'organization' => ! $me->isGuest(),
            'project' => $event->project && $me->canOnProject($event->project, 'view'),
            default => false,
        };

        abort_unless($visible, 404);
    }

    /** @return \Illuminate\Support\Collection<int, User>|false */
    private function resolveParticipants(array $ids, ?Project $project)
    {
        $users = User::where('organization_id', $this->me()->organization_id)->where('is_active', true)->whereIn('id', $ids)->get();

        if ($project) {
            foreach ($users as $user) {
                if (! $user->canOnProject($project, 'view')) {
                    return false;
                }
            }
        }

        return $users;
    }

    private function rules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'title' => "$required|string|max:255",
            'description' => 'nullable|string',
            'type' => ['sometimes', Rule::enum(EventType::class)],
            'location' => 'nullable|string|max:255',
            'meeting_url' => 'nullable|url|max:500',
            'starts_at' => "$required|date",
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'all_day' => 'nullable|boolean',
            'visibility' => ['nullable', Rule::in(config('platform.event_visibilities'))],
            'reminder_minutes' => 'nullable|integer|min:0|max:10080',
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('organization_id', $this->me()->organization_id)],
            'participants' => 'nullable|array|max:100',
            'participants.*' => 'integer',
        ];
    }
}
