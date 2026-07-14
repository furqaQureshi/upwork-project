<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ListingModeratedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly int $listingId,
        private readonly string $listingSlug,
        private readonly string $listingTitle,
        private readonly string $status,
        private readonly ?string $reason = null
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
        $isApproved = $this->status === 'approved';

        return [
            'type' => 'moderation',
            'title' => $isApproved ? 'Listing approved' : 'Listing rejected',
            'body' => $isApproved
                ? 'Your listing "'.Str::limit($this->listingTitle, 80).'" is now live.'
                : 'Your listing "'.Str::limit($this->listingTitle, 80).'" was rejected. '.Str::limit((string) $this->reason, 120),
            'url' => route('listings.show', $this->listingSlug),
            'icon' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'listing_id' => $this->listingId,
            'listing_slug' => $this->listingSlug,
            'listing_status' => $this->status,
            'reason' => $this->reason,
        ];
    }

    public function toWebPushPayload(): array
    {
        $isApproved = $this->status === 'approved';

        return [
            'title' => $isApproved ? 'Listing approved' : 'Listing rejected',
            'body' => $isApproved
                ? 'Your listing "'.Str::limit($this->listingTitle, 80).'" is now live.'
                : 'Your listing "'.Str::limit($this->listingTitle, 80).'" was rejected. '.Str::limit((string) $this->reason, 120),
            'icon' => asset('icons/icon.svg'),
            'badge' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'tag' => 'moderation-'.$this->listingId,
            'data' => [
                'url' => route('listings.show', $this->listingSlug),
                'type' => 'moderation',
                'listing_id' => $this->listingId,
                'listing_status' => $this->status,
                'sound' => (string) setting('notification_sound_url', ''),
            ],
        ];
    }
}
