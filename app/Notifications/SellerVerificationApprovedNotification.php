<?php

namespace App\Notifications;

use App\Models\SellerVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerVerificationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SellerVerification $verification,
        public ?string $adminNotes = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Seller Verification has been Approved! ✓')
            ->greeting('Congratulations!')
            ->line('Your seller verification has been approved by our team.')
            ->line('You can now enjoy all premium seller benefits:')
            ->line('• Verified Seller Badge on your profile')
            ->line('• Higher visibility in search results')
            ->line('• Access to advanced analytics')
            ->line('• Priority support from our team')
            ->when($this->adminNotes, fn($mail) => $mail->line('**Admin Notes:** ' . $this->adminNotes))
            ->action('View Your Profile', route('seller.dashboard'))
            ->line('Thank you for choosing to verify with us!')
            ->salutation('Best regards, Unisell Team');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Seller Verification Approved',
            'message' => 'Your seller verification has been approved!',
            'type' => 'seller_verification_approved',
            'verification_id' => $this->verification->id,
            'action_url' => route('seller-verification.show', $this->verification),
        ];
    }
}
