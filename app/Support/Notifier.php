<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AppNotification;

class Notifier
{
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
