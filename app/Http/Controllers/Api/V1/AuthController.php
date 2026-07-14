<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\FirebasePhoneAuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
        ]);

        if (! (bool) setting('registration_enabled', true)) {
            throw ValidationException::withMessages([
                'email' => ['Registrations are currently closed.'],
            ]);
        }

        $phone = $this->normalizePhone((string) $validated['phone']);
        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => ['Enter a valid mobile number. +91 is added automatically for 10 digit numbers.'],
            ]);
        }

        if ($this->findUserByPhone($phone)) {
            throw ValidationException::withMessages([
                'phone' => ['An account already exists for this mobile number.'],
            ]);
        }

        $user = User::query()->create([
            'name' => (string) $validated['name'],
            'email' => strtolower((string) $validated['email']),
            'password' => Hash::make((string) $validated['password']),
            'phone' => $phone,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'email_verified_at' => now(),
            'last_seen_at' => now(),
        ]);

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', strtolower((string) $validated['email']))->first();

        if (! $user || ! Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials provided.'],
            ]);
        }

        if ($user->is_blocked) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been blocked. Please contact support.'],
            ]);
        }

        $user->forceFill(['last_seen_at' => now()])->save();

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'flow' => ['nullable', 'string', 'in:login,register'],
        ]);

        $flow = (string) ($validated['flow'] ?? 'login');
        $this->ensureMobileAuthEnabled($flow);

        $phone = $this->normalizePhone((string) $validated['phone']);
        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => ['Enter a valid mobile number. +91 is added automatically for 10 digit numbers.'],
            ]);
        }

        return response()->json([
            'ok' => true,
            'phone' => $phone,
            'message' => 'Send OTP with Firebase on the mobile app, then call verify with the Firebase ID token.',
        ]);
    }

    public function verifyOtp(Request $request, FirebasePhoneAuthService $firebasePhoneAuthService): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'flow' => ['nullable', 'string', 'in:login,register'],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $flow = (string) ($validated['flow'] ?? 'login');
        $this->ensureMobileAuthEnabled($flow);

        $firebaseClaims = $firebasePhoneAuthService->verifyIdToken((string) $validated['id_token']);
        $firebasePhone = $this->normalizePhone((string) ($firebaseClaims['phone'] ?? ''));

        if ($firebasePhone === '') {
            throw ValidationException::withMessages([
                'mobile' => ['Verified Firebase token does not contain a mobile number.'],
            ]);
        }

        $incomingPhone = $this->normalizePhone((string) ($validated['phone'] ?? ''));
        if ($incomingPhone !== '' && ltrim($incomingPhone, '+') !== ltrim($firebasePhone, '+')) {
            throw ValidationException::withMessages([
                'mobile' => ['Mobile number mismatch. Please verify OTP using the same number.'],
            ]);
        }

        $user = $this->findUserByPhone($firebasePhone);

        if (! $user) {
            if ($flow !== 'register') {
                throw ValidationException::withMessages([
                    'mobile' => ['No account exists for this mobile number. Please register first.'],
                ]);
            }

            if (! (bool) setting('registration_enabled', true)) {
                throw ValidationException::withMessages([
                    'mobile' => ['Registrations are currently closed.'],
                ]);
            }

            $name = trim((string) ($validated['name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => ['Name is required for mobile registration.'],
                ]);
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $this->resolveMobileRegistrationEmail(
                    (string) ($firebaseClaims['uid'] ?? ''),
                    (string) ($firebaseClaims['email'] ?? '')
                ),
                'password' => Hash::make(Str::random(40)),
                'phone' => $firebasePhone,
                'email_verified_at' => now(),
                'last_seen_at' => now(),
            ]);

            event(new Registered($user));
        }

        if ($user->is_blocked) {
            throw ValidationException::withMessages([
                'mobile' => ['Your account has been blocked. Please contact support.'],
            ]);
        }

        $user->forceFill(['last_seen_at' => now()])->save();

        $token = $user->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], $flow === 'register' ? 201 : 200);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function logoutAllDevices(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'ok' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        $user->tokens()->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Logged out from all devices.',
        ]);
    }

    private function serializeUser(User $user): array
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
            'is_admin' => (bool) $user->is_admin,
            'is_blocked' => (bool) $user->is_blocked,
            'is_seller' => $user->listings()->exists(),
            'is_seller_verified' => (bool) $user->is_seller_verified,
            'is_car_seller_verified' => (bool) $user->isCarSellerVerified(),
            'is_premium_seller_verified' => (bool) $user->isPremiumSellerVerified(),
            'seller_verification_status' => $user->seller_verification_status,
            'car_seller_verification_status' => $carVerification?->status,
            'seller_type' => (string) ($user->seller_type ?? ''),
            'seller_badge_label' => $user->sellerBadgeLabel(),
            'seller_verified_at' => optional($user->seller_verified_at)?->toIso8601String(),
        ];
    }

    private function ensureMobileAuthEnabled(string $flow): void
    {
        if ($flow === 'register') {
            if (! (bool) setting('auth_register_mobile_enabled', false)) {
                throw ValidationException::withMessages([
                    'mobile' => ['Mobile OTP registration is currently disabled.'],
                ]);
            }

            return;
        }

        if (! (bool) setting('auth_login_mobile_enabled', false)) {
            throw ValidationException::withMessages([
                'mobile' => ['Mobile OTP login is currently disabled.'],
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($trimmed, '+')) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return '+91'.$digits;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '+91'.substr($digits, 1);
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }

    private function findUserByPhone(string $phone): ?User
    {
        $normalized = $this->normalizePhone($phone);
        $digitsOnly = ltrim($normalized, '+');

        $direct = User::query()
            ->where('phone', $normalized)
            ->orWhere('phone', $digitsOnly)
            ->first();

        if ($direct) {
            return $direct;
        }

        return User::query()
            ->whereNotNull('phone')
            ->get()
            ->first(function (User $candidate) use ($normalized, $digitsOnly): bool {
                $candidatePhone = $this->normalizePhone((string) $candidate->phone);

                return $candidatePhone === $normalized
                    || ltrim($candidatePhone, '+') === $digitsOnly;
            });
    }

    private function resolveMobileRegistrationEmail(string $uid, string $firebaseEmail): string
    {
        $candidate = strtolower(trim($firebaseEmail));
        if ($candidate !== '' && ! User::query()->where('email', $candidate)->exists()) {
            return $candidate;
        }

        $safeUid = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $uid) ?? '');
        if ($safeUid === '') {
            $safeUid = strtolower(Str::random(16));
        }

        $candidate = $safeUid.'@mobile.unsell.local';
        while (User::query()->where('email', $candidate)->exists()) {
            $candidate = $safeUid.'+'.strtolower(Str::random(6)).'@mobile.unsell.local';
        }

        return $candidate;
    }
}
