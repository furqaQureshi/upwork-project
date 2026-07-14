<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ListingCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $listingId,
        private readonly string $listingSlug,
        private readonly string $listingTitle,
        private readonly string $status
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
        $isPending = $this->status === 'pending';

        return [
            'type' => 'listing',
            'title' => $isPending ? 'Listing submitted' : 'Listing published',
            'body' => $isPending
                ? 'Your listing "'.Str::limit($this->listingTitle, 80).'" was submitted and is waiting for admin approval.'
                : 'Your listing "'.Str::limit($this->listingTitle, 80).'" is now live.',
            'url' => route('listings.show', $this->listingSlug),
            'icon' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'listing_id' => $this->listingId,
            'listing_slug' => $this->listingSlug,
            'listing_status' => $this->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPushPayload(): array
    {
        $isPending = $this->status === 'pending';

        return [
            'title' => $isPending ? 'Listing submitted' : 'Listing published',
            'body' => $isPending
                ? 'Your listing "'.Str::limit($this->listingTitle, 80).'" is waiting for admin approval.'
                : 'Your listing "'.Str::limit($this->listingTitle, 80).'" is now live.',
            'icon' => asset('icons/icon.svg'),
            'badge' => asset('icons/icon.svg'),
            'sound' => setting('notification_sound_url', ''),
            'tag' => 'listing-created-'.$this->listingId,
            'data' => [
                'url' => route('listings.show', $this->listingSlug),
                'type' => 'listing',
                'listing_id' => $this->listingId,
                'listing_status' => $this->status,
                'sound' => (string) setting('notification_sound_url', ''),
            ],
        ];
    }
}
