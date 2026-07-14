<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LicenseService
{
    private const API_BASE_URL = 'https://support.unisell.online/api/v1/';

    /**
     * Verify CodeCanyon purchase code using Unisell API
     */
    public function verifyPurchaseCode(string $purchaseCode, string $buyerUsername): array
    {
        try {
            // Get the personal token from settings
            $personalToken = AppSetting::getValue('codecanyon_personal_token', '');

            if (empty($personalToken)) {
                return [
                    'valid' => false,
                    'message' => 'CodeCanyon Personal Token not configured',
                ];
            }

            // Call Unisell API for verification
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $personalToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post(self::API_BASE_URL . 'verify', [
                'purchase_code' => $purchaseCode,
                'buyer_username' => $buyerUsername,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['success']) && $data['success'] && isset($data['data'])) {
                    $verificationData = $data['data'];

                    // Check if the buyer matches
                    if (isset($verificationData['buyer']) && $verificationData['buyer'] === $buyerUsername) {
                        return [
                            'valid' => true,
                            'message' => 'Purchase code verified successfully',
                            'data' => $verificationData,
                        ];
                    } else {
                        return [
                            'valid' => false,
                            'message' => 'Purchase code does not match the buyer username',
                        ];
                    }
                } else {
                    return [
                        'valid' => false,
                        'message' => $data['message'] ?? 'Verification failed',
                    ];
                }
            } else {
                Log::warning('Unisell API verification failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'valid' => false,
                    'message' => 'Failed to verify purchase code. Please check your CodeCanyon Personal Token.',
                ];
            }
        } catch (\Exception $e) {
            Log::error('License verification error', [
                'error' => $e->getMessage(),
                'purchase_code' => substr($purchaseCode, 0, 10) . '...', // Log partial code for debugging
            ]);

            return [
                'valid' => false,
                'message' => 'An error occurred while verifying the purchase code',
            ];
        }
    }

    /**
     * Check if license is currently valid
     */
    public function isLicenseValid(): bool
    {
        $verified = AppSetting::getValue('license_verified', false);
        $lastVerified = AppSetting::getValue('license_last_verified', '');

        // If never verified, it's not valid
        if (!$verified || empty($lastVerified)) {
            return false;
        }

        // Check if verification is still valid (e.g., within 30 days)
        $lastVerifiedDate = \Carbon\Carbon::parse($lastVerified);
        $daysSinceVerification = $lastVerifiedDate->diffInDays(now());

        return $daysSinceVerification <= 30; // Valid for 30 days
    }

    /**
     * Update license verification status
     */
    public function updateLicenseStatus(bool $verified, array $verificationData = []): void
    {
        AppSetting::setValue('license_verified', $verified);
        AppSetting::setValue('license_last_verified', $verified ? now()->toISOString() : '');

        if ($verified && isset($verificationData['buyer'])) {
            AppSetting::setValue('codecanyon_buyer_username', $verificationData['buyer']);
        }
    }
}