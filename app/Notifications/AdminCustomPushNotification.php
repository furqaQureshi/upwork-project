<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AdminCustomPushNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
        private readonly string $senderName
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'admin_custom_push',
            'title' => Str::limit($this->title, 80),
            'body' => Str::limit($this->body, 240),
            'url' => $this->url,
            'icon' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'sender_name' => $this->senderName,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPushPayload(): array
    {
        return [
            'title' => Str::limit($this->title, 80),
            'body' => Str::limit($this->body, 240),
            'icon' => asset('icons/icon.svg'),
            'badge' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'tag' => 'admin-custom-push-'.substr(md5($this->title.$this->body.$this->url), 0, 12),
            'data' => [
                'url' => $this->url,
                'type' => 'admin_custom_push',
                'sound' => (string) setting('notification_sound_url', ''),
            ],
        ];
    }
}
