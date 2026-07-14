<?php

namespace App\Policies;

use App\Models\SellerVerification;
use App\Models\User;

class SellerVerificationPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SellerVerification $sellerVerification): bool
    {
        return $user->id === $sellerVerification->user_id || $user->is_admin;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return !$user->is_blocked;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SellerVerification $sellerVerification): bool
    {
        // Only the user who submitted can update, and only if pending or rejected
        return $user->id === $sellerVerification->user_id && 
               in_array($sellerVerification->status, ['pending', 'rejected']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SellerVerification $sellerVerification): bool
    {
        // Only the user who submitted can delete, and only if pending or rejected
        return $user->id === $sellerVerification->user_id && 
               in_array($sellerVerification->status, ['pending', 'rejected']);
    }

    /**
     * Only admin can perform admin actions
     */
    public function admin(User $user): bool
    {
        return $user->is_admin;
    }
}
