<?php

namespace App\Notifications;

use App\Models\SellerVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerVerificationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SellerVerification $verification,
        public string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Seller Verification Submission')
            ->greeting('Hello!')
            ->line('Thank you for submitting your seller verification. Unfortunately, we could not approve your application at this time.')
            ->line('**Reason:** ' . $this->reason)
            ->line('**What you can do:**')
            ->line('1. Review the feedback provided')
            ->line('2. Gather the required documents')
            ->line('3. Resubmit your verification request')
            ->action('Resubmit Verification', route('seller.verification'))
            ->line('If you have any questions, please don\'t hesitate to contact our support team.')
            ->salutation('Best regards, Unisell Team');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Seller Verification Status Update',
            'message' => 'Your verification was not approved. Reason: ' . $this->reason,
            'type' => 'seller_verification_rejected',
            'verification_id' => $this->verification->id,
            'reason' => $this->reason,
            'action_url' => route('seller.verification'),
        ];
    }
}
