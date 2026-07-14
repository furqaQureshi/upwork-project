<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FirebasePhoneAuthService
{
    /**
     * Verify Firebase ID token and return normalized user claims.
     *
     * @return array{uid:string,phone:string,email:string,email_verified:bool}
     *
     * @throws ValidationException
     */
    public function verifyIdToken(string $idToken): array
    {
        $token = trim($idToken);

        if ($token === '') {
            throw ValidationException::withMessages([
                'mobile' => 'Missing Firebase ID token.',
            ]);
        }

        $apiKey = trim((string) setting('fcm_api_key', config('services.fcm.api_key', '')));
        if ($apiKey === '') {
            throw ValidationException::withMessages([
                'mobile' => 'Firebase API key is not configured. Set FCM API key in admin notifications settings.',
            ]);
        }

        $response = Http::asJson()
            ->acceptJson()
            ->timeout(12)
            ->post("https://identitytoolkit.googleapis.com/v1/accounts:lookup?key={$apiKey}", [
                'idToken' => $token,
            ]);

        if (! $response->ok()) {
            $errorCode = (string) data_get($response->json(), 'error.message', '');

            throw ValidationException::withMessages([
                'mobile' => $this->messageForErrorCode($errorCode),
            ]);
        }

        $payload = $response->json();
        $user = data_get($payload, 'users.0');

        if (! is_array($user)) {
            throw ValidationException::withMessages([
                'mobile' => 'Unable to validate OTP session. Please request OTP again.',
            ]);
        }

        return [
            'uid' => trim((string) ($user['localId'] ?? '')),
            'phone' => trim((string) ($user['phoneNumber'] ?? '')),
            'email' => trim((string) ($user['email'] ?? '')),
            'email_verified' => (bool) ($user['emailVerified'] ?? false),
        ];
    }

    private function messageForErrorCode(string $code): string
    {
        return match (strtoupper(trim($code))) {
            'INVALID_ID_TOKEN' => 'Invalid OTP session. Please verify again.',
            'USER_DISABLED' => 'This Firebase account is disabled.',
            'TOKEN_EXPIRED', 'ID_TOKEN_EXPIRED' => 'OTP session expired. Please request OTP again.',
            'CREDENTIAL_TOO_OLD_LOGIN_AGAIN' => 'OTP session is no longer valid. Please login again.',
            default => 'Unable to verify mobile OTP right now. Please try again.',
        };
    }
}
