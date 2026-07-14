<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->serializeUser($request->user()),
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'verification_document_type' => $validated['verification_document_type'] ?? $user->verification_document_type,
            'verification_document_number' => $validated['verification_document_number'] ?? $user->verification_document_number,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'data' => $this->serializeUser($user->fresh()),
            'message' => 'Profile updated successfully.',
        ]);
    }

    private function serializeUser($user): array
    {
        $carVerification = $user->sellerVerifications()
            ->whereHas('category', function ($query): void {
                $query->where('slug', 'cars');
            })
            ->latest('id')
            ->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'city' => $user->city,
            'state' => $user->state,
            'is_seller' => $user->listings()->exists(),
            'is_seller_verified' => (bool) $user->is_seller_verified,
            'is_car_seller_verified' => (bool) $user->isCarSellerVerified(),
            'is_premium_seller_verified' => (bool) $user->isPremiumSellerVerified(),
            'seller_verification_status' => $user->seller_verification_status,
            'car_seller_verification_status' => $carVerification?->status,
            'seller_type' => (string) ($user->seller_type ?? ''),
            'seller_badge_label' => $user->sellerBadgeLabel(),
            'seller_verified_at' => optional($user->seller_verified_at)?->toIso8601String(),
            'verification_document_type' => $user->verification_document_type,
            'verification_document_number' => $user->verification_document_number,
        ];
    }
}
