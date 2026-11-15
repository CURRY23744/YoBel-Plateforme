<?php

namespace App\Http\Controllers\Api;

use App\Models\Discussion;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MessageController extends ApiController
{
    public function index(Request $request, Discussion $discussion)
    {
        $this->authorizeDiscussion($discussion);

        $messages = $discussion->messages()
            ->with(['user:id,first_name,last_name,avatar_path', 'attachments', 'mentions:id,first_name,last_name'])
            ->orderBy('created_at')->orderBy('id')
            ->paginate($this->perPage($request));

        return response()->json($messages);
    }

    public function store(Request $request, Discussion $discussion): JsonResponse
    {
        $me = $this->me();
        $this->authorizeDiscussion($discussion, 'comment');

        if ($discussion->is_locked && ! $this->canManageScope($discussion->project)) {
            return response()->json(['message' => 'Cette discussion est verrouillée.'], 423);
        }

        $data = $request->validate([
            'body' => 'required|string|max:10000',
            'parent_id' => ['nullable', Rule::exists('messages', 'id')->where('discussion_id', $discussion->id)->whereNull('deleted_at')],
            'mentions' => 'nullable|array|max:50',
            'mentions.*' => ['integer', Rule::exists('users', 'id')->where('organization_id', $me->organization_id)],
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => ['file', 'mimes:'.implode(',', config('platform.allowed_extensions')), 'max:'.config('platform.upload_max_kb')],
        ]);

        $mentionedUsers = collect($data['mentions'] ?? [])->unique()->map(fn ($id) => User::find($id))
            ->filter(fn ($u) => $u && $u->is_active && $this->canSee($u, $discussion));

        $message = DB::transaction(function () use ($data, $discussion, $me, $mentionedUsers, $request) {
            $message = Message::create([
                'discussion_id' => $discussion->id,
                'user_id' => $me->id,
                'parent_id' => $data['parent_id'] ?? null,
                'body' => $data['body'],
            ]);

            $message->mentions()->sync($mentionedUsers->mapWithKeys(fn ($u) => [$u->id => ['notified_at' => now()]])->all());

            foreach ($request->file('attachments', []) as $upload) {
                $disk = config('platform.storage_disk');
                $path = $upload->storeAs(
                    "org-{$discussion->organization_id}/discussions/{$discussion->id}",
                    Str::uuid().'.'.strtolower($upload->getClientOriginalExtension()),
                    $disk
                );
                MessageAttachment::create([
                    'message_id' => $message->id,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $upload->getClientOriginalName(),
                    'mime_type' => $upload->getMimeType(),
                    'size' => $upload->getSize(),
                ]);
            }

            $discussion->update(['messages_count' => $discussion->messages_count + 1, 'last_message_at' => now()]);

            return $message;
        });

        $context = ['project_id' => $discussion->project_id, 'discussion_id' => $discussion->id, 'message_id' => $message->id];
        Notifier::send($mentionedUsers, 'message.mention', "{$me->name} vous a mentionné dans « {$discussion->title} »", Str::limit($message->body, 140), $context);

        if ($discussion->creator && ! $mentionedUsers->contains('id', $discussion->created_by)) {
            Notifier::send($discussion->creator, 'message.reply', "Nouveau message dans « {$discussion->title} »", Str::limit($message->body, 140), $context);
        }

        return response()->json($message->load(['user:id,first_name,last_name', 'attachments', 'mentions:id,first_name,last_name']), 201);
    }

    public function update(Request $request, Message $message): JsonResponse
    {
        $me = $this->me();
        $discussion = $message->discussion;
        $this->authorizeDiscussion($discussion, 'comment');

        abort_unless($message->user_id === $me->id, 403, 'Vous ne pouvez modifier que vos propres messages.');

        $window = config('platform.message_edit_window_minutes');
        if (! $me->isAdmin() && $message->created_at->diffInMinutes(now()) > $window) {
            return response()->json(['message' => "Un message ne peut plus être modifié après {$window} minutes."], 403);
        }

        $data = $request->validate(['body' => 'required|string|max:10000']);

        $message->update(['body' => $data['body'], 'edited_at' => now()]);

        return response()->json($message->fresh(['user:id,first_name,last_name', 'attachments']));
    }

    public function destroy(Message $message): JsonResponse
    {
        $discussion = $message->discussion;
        $this->authorizeDiscussion($discussion);
        abort_unless($this->canManageScope($discussion->project, $message->user_id), 403);

        DB::transaction(function () use ($message, $discussion) {
            $message->delete();
            $discussion->update(['messages_count' => max(0, $discussion->messages_count - 1)]);
        });

        return response()->json(null, 204);
    }

    private function canSee(User $user, Discussion $discussion): bool
    {
        if ($discussion->project_id) {
            return $discussion->project && $user->canOnProject($discussion->project, 'view');
        }

        return ! $user->isGuest();
    }
}
