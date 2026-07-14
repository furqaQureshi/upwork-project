<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'subscription_package_id',
        'status',
        'verified_at',
        'admin_notes',
        'verified_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user for this verification
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the subscription package
     */
    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }

    /**
     * Get the admin who verified this
     */
    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_admin_id');
    }

    /**
     * Get all documents for this verification
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SellerVerificationDocument::class);
    }

    /**
     * Check if all required documents are uploaded
     */
    public function allDocumentsUploaded(): bool
    {
        if (!$this->subscriptionPackage || !$this->subscriptionPackage->required_documents) {
            return true;
        }

        $requiredDocuments = $this->subscriptionPackage->required_documents;
        $uploadedDocumentTypes = $this->documents()->pluck('document_type')->toArray();

        foreach ($requiredDocuments as $docType) {
            if (!in_array($docType, $uploadedDocumentTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if all documents are verified
     */
    public function allDocumentsVerified(): bool
    {
        if ($this->documents()->count() === 0) {
            return false;
        }

        return $this->documents()
            ->where('verification_status', '!=', 'verified')
            ->count() === 0;
    }

    /**
     * Check if verification is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if verification is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if verification is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Approve verification
     */
    public function approve(User $admin, ?string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'verified_at' => now(),
            'verified_by_admin_id' => $admin->id,
            'admin_notes' => $notes,
        ]);

        $this->user?->syncSellerStateFromApprovedVerifications();
    }

    /**
     * Reject verification
     */
    public function reject(User $admin, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'verified_by_admin_id' => $admin->id,
            'admin_notes' => $reason,
        ]);

        $this->user?->syncSellerStateFromApprovedVerifications();
    }
}
