<?php

namespace App\Notifications;

use App\Models\SellerVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerVerificationPendingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SellerVerification $verification) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Seller Verification has been Submitted for Review')
            ->greeting('Hello!')
            ->line('Thank you for submitting your seller verification request.')
            ->line('We have received all your documents and our team will review them shortly.')
            ->line('**Expected Review Time:** 2-3 business days')
            ->line('**What happens next:**')
            ->line('1. Our team will verify all your documents')
            ->line('2. We\'ll validate your business information')
            ->line('3. You\'ll receive an email with the decision')
            ->action('Track Your Verification', route('seller-verification.show', $this->verification))
            ->line('You can check your verification status anytime in your seller dashboard.')
            ->salutation('Best regards, Unisell Team');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Verification Submitted',
            'message' => 'Your seller verification is being reviewed',
            'type' => 'seller_verification_submitted',
            'verification_id' => $this->verification->id,
            'action_url' => route('seller-verification.show', $this->verification),
        ];
    }
}
