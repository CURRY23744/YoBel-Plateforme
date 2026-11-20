<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    public function __construct(public array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->payload['title'] ?? 'Nouvelle notification YoBel')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line($this->payload['title'] ?? 'Vous avez reçu une nouvelle notification.')
            ->action('Voir sur YoBel', config('platform.frontend_url'));

        if (! empty($this->payload['body'])) {
            $message->line($this->payload['body']);
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
