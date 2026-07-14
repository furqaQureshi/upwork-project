<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewMessageNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly int $conversationId,
        private readonly int $messageId,
        private readonly string $senderName,
        private readonly string $listingTitle,
        private readonly string $messageBody
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'message',
            'title' => 'New message from '.$this->senderName,
            'body' => Str::limit($this->messageBody, 120),
            'url' => route('chat.show', $this->conversationId),
            'icon' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'conversation_id' => $this->conversationId,
            'message_id' => $this->messageId,
            'listing_title' => $this->listingTitle,
            'sender_name' => $this->senderName,
        ];
    }

    public function toWebPushPayload(): array
    {
        return [
            'title' => 'New message from '.$this->senderName,
            'body' => Str::limit($this->messageBody, 120),
            'icon' => asset('icons/icon.svg'),
            'badge' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'tag' => 'message-'.$this->conversationId,
            'data' => [
                'url' => route('chat.show', $this->conversationId),
                'type' => 'message',
                'conversation_id' => $this->conversationId,
                'message_id' => $this->messageId,
                'sound' => (string) setting('notification_sound_url', ''),
            ],
        ];
    }
}
