<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\FirebasePhoneAuthService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    private const MOBILE_REGISTRATION_HANDOFF_SESSION = 'auth.mobile_registration_handoff';

    private const MOBILE_REGISTRATION_HANDOFF_TTL_MINUTES = 15;

    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        abort_if(! setting('registration_enabled', true), 403, 'Registrations are currently closed.');

        return view('auth.register', [
            'mobileRegistrationHandoff' => $this->getMobileRegistrationHandoff($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if(! setting('registration_enabled', true), 403, 'Registrations are currently closed.');

        if (! (bool) setting('auth_register_email_enabled', true)) {
            throw ValidationException::withMessages([
                'email' => 'Email registration is currently disabled. Use mobile OTP registration.',
            ]);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $phone = $this->normalizePhone((string) $request->phone);
        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => 'Enter a valid mobile number. +91 is added automatically for 10 digit numbers.',
            ]);
        }

        if ($this->findUserByPhone($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'An account already exists for this mobile number.',
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $phone,
            'city' => $request->city,
            'state' => $request->state,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $this->clearMobileRegistrationHandoff($request);

        return redirect(route('dashboard', absolute: false));
    }

    public function storeFirebase(Request $request, FirebasePhoneAuthService $firebasePhoneAuthService): JsonResponse
    {
        abort_if(! setting('registration_enabled', true), 403, 'Registrations are currently closed.');

        if (! (bool) setting('auth_register_mobile_enabled', false)) {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile OTP registration is currently disabled.',
            ]);
        }

        $mobileRegistrationHandoff = $this->getMobileRegistrationHandoff($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'id_token' => ['nullable', 'string'],
        ]);

        $incomingPhone = $this->normalizePhone((string) $validated['phone']);

        if ($incomingPhone === '') {
            throw ValidationException::withMessages([
                'mobile' => 'Enter a valid mobile number. +91 will be added automatically.',
            ]);
        }

        $idToken = trim((string) ($validated['id_token'] ?? ''));
        $firebasePhone = '';
        $firebaseUid = '';
        $firebaseEmail = '';

        if ($idToken !== '') {
            $firebaseClaims = $firebasePhoneAuthService->verifyIdToken($idToken);
            $firebasePhone = $this->normalizePhone((string) ($firebaseClaims['phone'] ?? ''));
            $firebaseUid = (string) ($firebaseClaims['uid'] ?? '');
            $firebaseEmail = (string) ($firebaseClaims['email'] ?? '');
        } elseif ($mobileRegistrationHandoff) {
            $firebasePhone = $this->normalizePhone((string) ($mobileRegistrationHandoff['phone'] ?? ''));
            $firebaseUid = (string) ($mobileRegistrationHandoff['uid'] ?? '');
            $firebaseEmail = (string) ($mobileRegistrationHandoff['email'] ?? '');
        } else {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile verification expired. Please verify OTP again.',
            ]);
        }

        if ($firebasePhone === '') {
            throw ValidationException::withMessages([
                'mobile' => 'Verified Firebase token does not contain a mobile number.',
            ]);
        }

        if (ltrim($firebasePhone, '+') !== ltrim($incomingPhone, '+')) {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile number mismatch. Please continue with the verified number.',
            ]);
        }

        if ($this->findUserByPhone($firebasePhone)) {
            throw ValidationException::withMessages([
                'mobile' => 'An account already exists for this mobile number. Please login instead.',
            ]);
        }

        $email = $this->resolveMobileRegistrationEmail(
            $firebaseUid,
            $firebaseEmail
        );

        $user = User::create([
            'name' => (string) $validated['name'],
            'email' => $email,
            'phone' => $firebasePhone,
            'password' => Str::random(40),
            'email_verified_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_seen_at' => now(),
        ])->save();

        $this->clearMobileRegistrationHandoff($request);

        return response()->json([
            'redirect' => route('dashboard', absolute: false),
        ]);
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

    private function getMobileRegistrationHandoff(Request $request): ?array
    {
        $handoff = $request->session()->get(self::MOBILE_REGISTRATION_HANDOFF_SESSION);
        if (! is_array($handoff)) {
            return null;
        }

        $phone = $this->normalizePhone((string) ($handoff['phone'] ?? ''));
        $verifiedAt = (int) ($handoff['verified_at'] ?? 0);
        $minimumTimestamp = now()->subMinutes(self::MOBILE_REGISTRATION_HANDOFF_TTL_MINUTES)->getTimestamp();

        if ($phone === '' || $verifiedAt < $minimumTimestamp) {
            $this->clearMobileRegistrationHandoff($request);

            return null;
        }

        return [
            'phone' => $phone,
            'uid' => (string) ($handoff['uid'] ?? ''),
            'email' => (string) ($handoff['email'] ?? ''),
            'verified_at' => $verifiedAt,
        ];
    }

    private function clearMobileRegistrationHandoff(Request $request): void
    {
        $request->session()->forget(self::MOBILE_REGISTRATION_HANDOFF_SESSION);
    }
}
