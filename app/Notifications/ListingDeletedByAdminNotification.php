<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ListingDeletedByAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $listingId,
        private readonly string $listingTitle,
        private readonly ?string $reason = null
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
        $body = 'Your listing "'.Str::limit($this->listingTitle, 80).'" was removed by admin moderation.';

        if ($this->reason) {
            $body .= ' Reason: '.Str::limit($this->reason, 160);
        }

        return [
            'type' => 'moderation',
            'title' => 'Listing removed by admin',
            'body' => $body,
            'url' => route('listings.index'),
            'icon' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'listing_id' => $this->listingId,
            'reason' => $this->reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPushPayload(): array
    {
        $body = 'Your listing "'.Str::limit($this->listingTitle, 80).'" was removed by admin moderation.';

        if ($this->reason) {
            $body .= ' Reason: '.Str::limit($this->reason, 120);
        }

        return [
            'title' => 'Listing removed by admin',
            'body' => $body,
            'icon' => asset('icons/icon.svg'),
            'badge' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'tag' => 'listing-deleted-'.$this->listingId,
            'data' => [
                'url' => route('listings.index'),
                'type' => 'moderation',
                'listing_id' => $this->listingId,
                'listing_status' => 'deleted',
                'sound' => (string) setting('notification_sound_url', ''),
            ],
        ];
    }
}
