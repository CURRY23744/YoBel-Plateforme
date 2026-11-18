<?php

namespace App\Support;

use App\Models\Discussion;
use App\Models\Message;
use App\Models\ProjectMember;
use App\Models\User;
use App\Notifications\AppNotification;

class Notifier
{
    public static function discussionMessage(Discussion $discussion, User $sender, Message $message, array $excludedUserIds = []): void
    {
        $recipientIds = $discussion->project_id
            ? ProjectMember::query()
                ->where('project_id', $discussion->project_id)
                ->whereNull('left_at')
                ->where('can_view', true)
                ->pluck('user_id')
            : collect([$discussion->created_by]);

        if ($discussion->project_id) {
            $recipientIds = $recipientIds->merge(
                User::query()
                    ->where('organization_id', $discussion->organization_id)
                    ->where('role', 'admin')
                    ->where('is_active', true)
                    ->pluck('id')
            );
        }

        $recipientIds = $recipientIds
            ->unique()
            ->reject(fn ($id) => (int) $id === $sender->id || in_array((int) $id, $excludedUserIds, true));

        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->where('organization_id', $discussion->organization_id)
            ->where('is_active', true)
            ->get();

        self::send(
            $recipients,
            'message.reply',
            "Nouveau message dans « {$discussion->title} »",
            \Illuminate\Support\Str::limit($message->body, 140),
            [
                'project_id' => $discussion->project_id,
                'discussion_id' => $discussion->id,
                'message_id' => $message->id,
            ]
        );
    }

    /**
     * @param  User|iterable<User>|null  $users
     * @param  array<string, mixed>  $data  ex: ['project_id' => 3, 'discussion_id' => 5]
     */
    public static function send($users, string $type, string $title, ?string $body = null, array $data = []): void
    {
        $users = collect($users instanceof User ? [$users] : ($users ?? []))->filter()->unique('id');

        foreach ($users as $user) {
            if ($user->id === auth()->id() || ! $user->is_active) {
                continue;
            }

            $muted = $user->notification_preferences['muted'] ?? [];
            if (in_array($type, $muted, true)) {
                continue;
            }

            $user->notify(new AppNotification(['type' => $type, 'title' => $title, 'body' => $body] + $data));
        }
    }
}
