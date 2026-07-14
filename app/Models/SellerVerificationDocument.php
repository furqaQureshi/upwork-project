<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SellerVerificationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_verification_id',
        'document_type',
        'document_path',
        'document_number',
        'verification_status',
        'verification_note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the seller verification
     */
    public function sellerVerification(): BelongsTo
    {
        return $this->belongsTo(SellerVerification::class);
    }

    /**
     * Get the document URL
     */
    public function getDocumentUrl(): string
    {
        if (!$this->document_path) {
            return '';
        }

        return Storage::url($this->document_path);
    }

    /**
     * Check if document is verified
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Verify document
     */
    public function verify(?string $note = null): void
    {
        $this->update([
            'verification_status' => 'verified',
            'verification_note' => $note,
        ]);
    }

    /**
     * Reject document
     */
    public function reject(string $reason): void
    {
        $this->update([
            'verification_status' => 'rejected',
            'verification_note' => $reason,
        ]);
    }

    /**
     * Delete document from storage and database
     */
    public function deleteDocument(): bool
    {
        if ($this->document_path && Storage::exists($this->document_path)) {
            Storage::delete($this->document_path);
        }

        return $this->delete();
    }
}
