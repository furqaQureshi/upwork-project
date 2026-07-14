<?php

namespace App\Services;

use App\Models\SellerVerification;
use App\Models\User;
use App\Notifications\SellerVerificationApprovedNotification;
use App\Notifications\SellerVerificationRejectedNotification;
use App\Notifications\SellerVerificationPendingNotification;
use Illuminate\Notifications\Notification;

class SellerVerificationNotificationService
{
    /**
     * Notify seller on verification approval
     */
    public function notifyApproved(SellerVerification $verification, ?string $adminNotes = null): void
    {
        $verification->user->notify(
            new SellerVerificationApprovedNotification($verification, $adminNotes)
        );

        // Send push notification (optional)
        $this->sendPushNotification(
            $verification->user,
            'Your seller verification has been approved!',
            'You can now enjoy premium seller benefits',
            'seller_verification_approved',
            ['verification_id' => $verification->id]
        );
    }

    /**
     * Notify seller on verification rejection
     */
    public function notifyRejected(SellerVerification $verification, string $reason): void
    {
        $verification->user->notify(
            new SellerVerificationRejectedNotification($verification, $reason)
        );

        // Send push notification (optional)
        $this->sendPushNotification(
            $verification->user,
            'Your seller verification was not approved',
            'Please review the feedback and resubmit',
            'seller_verification_rejected',
            ['verification_id' => $verification->id, 'reason' => $reason]
        );
    }

    /**
     * Notify seller on verification submission (pending review)
     */
    public function notifyPending(SellerVerification $verification): void
    {
        $verification->user->notify(
            new SellerVerificationPendingNotification($verification)
        );

        // Send push notification (optional)
        $this->sendPushNotification(
            $verification->user,
            'Your seller verification was submitted',
            'Our team will review it within 2-3 business days',
            'seller_verification_submitted',
            ['verification_id' => $verification->id]
        );
    }

    /**
     * Notify seller on document verification
     */
    public function notifyDocumentVerified(SellerVerification $verification, string $documentType): void
    {
        $message = ucfirst(str_replace('_', ' ', $documentType)) . ' has been verified';

        // Create database notification
        $verification->user->notify(
            new \App\Notifications\SellerVerificationDocumentVerifiedNotification($verification, $documentType)
        );

        // Send push notification (optional)
        $this->sendPushNotification(
            $verification->user,
            'Document verification update',
            $message,
            'seller_verification_document_verified',
            ['verification_id' => $verification->id, 'document_type' => $documentType]
        );
    }

    /**
     * Notify seller on document rejection
     */
    public function notifyDocumentRejected(SellerVerification $verification, string $documentType, string $reason): void
    {
        $message = ucfirst(str_replace('_', ' ', $documentType)) . ' was not approved';

        // Create database notification
        $verification->user->notify(
            new \App\Notifications\SellerVerificationDocumentRejectedNotification($verification, $documentType, $reason)
        );

        // Send push notification (optional)
        $this->sendPushNotification(
            $verification->user,
            'Document verification update',
            $message,
            'seller_verification_document_rejected',
            ['verification_id' => $verification->id, 'document_type' => $documentType, 'reason' => $reason]
        );
    }

    /**
     * Send push notification to user
     */
    private function sendPushNotification(User $user, string $title, string $body, string $tag, array $data = []): void
    {
        try {
            // Get user's push subscriptions
            $subscriptions = $user->pushSubscriptions()->get();

            if ($subscriptions->isEmpty()) {
                return; // No push subscriptions
            }

            // TODO: Implement push notification sending via Firebase Cloud Messaging or similar
            // This is a placeholder for the push notification implementation
            // Example: use Firebase Admin SDK or WebPush library

            \Log::info("Push notification to {$user->id}", [
                'title' => $title,
                'body' => $body,
                'tag' => $tag,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to send push notification: {$e->getMessage()}");
        }
    }

    /**
     * Batch notify admins of pending verification
     */
    public function notifyAdminsOfPending(SellerVerification $verification): void
    {
        // Get all admin users
        $admins = User::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            $admin->notify(
                new \App\Notifications\AdminSellerVerificationPendingNotification($verification)
            );
        }
    }

    /**
     * Send daily summary notification to admins
     */
    public function sendDailySummaryToAdmins(): void
    {
        $pendingCount = SellerVerification::where('status', 'pending')->count();
        $approvedCount = SellerVerification::where('status', 'approved')->whereDate('verified_at', today())->count();
        $rejectedCount = SellerVerification::where('status', 'rejected')->whereDate('updated_at', today())->count();

        if ($pendingCount === 0 && $approvedCount === 0 && $rejectedCount === 0) {
            return; // No activity to report
        }

        $admins = User::where('is_admin', true)->get();

        foreach ($admins as $admin) {
            $admin->notify(
                new \App\Notifications\AdminSellerVerificationDailySummaryNotification(
                    $pendingCount,
                    $approvedCount,
                    $rejectedCount
                )
            );
        }
    }
}
