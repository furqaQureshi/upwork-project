<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Auth\FirebasePhoneAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    private const MOBILE_REGISTRATION_HANDOFF_SESSION = 'auth.mobile_registration_handoff';

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (! (bool) setting('auth_login_email_enabled', true)) {
            throw ValidationException::withMessages([
                'email' => 'Email login is currently disabled. Use mobile OTP login.',
            ]);
        }

        $request->authenticate();

        if ($request->user()->is_blocked) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Your account has been blocked. Please contact support.',
            ]);
        }

        $request->session()->regenerate();

        $request->user()->forceFill([
            'last_seen_at' => now(),
        ])->save();

        $this->clearMobileRegistrationHandoff($request);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Handle Firebase phone OTP login.
     */
    public function storeFirebase(Request $request, FirebasePhoneAuthService $firebasePhoneAuthService): JsonResponse
    {
        if (! (bool) setting('auth_login_mobile_enabled', false)) {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile OTP login is currently disabled.',
            ]);
        }

        $validated = $request->validate([
            'id_token' => ['required', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $firebaseClaims = $firebasePhoneAuthService->verifyIdToken((string) $validated['id_token']);
        $firebasePhone = $this->normalizePhone((string) ($firebaseClaims['phone'] ?? ''));

        if ($firebasePhone === '') {
            throw ValidationException::withMessages([
                'mobile' => 'Verified Firebase token does not contain a mobile number.',
            ]);
        }

        $incomingPhone = $this->normalizePhone((string) ($validated['phone'] ?? ''));
        if ($incomingPhone !== '' && ltrim($incomingPhone, '+') !== ltrim($firebasePhone, '+')) {
            throw ValidationException::withMessages([
                'mobile' => 'Mobile number mismatch. Please verify OTP using the same number.',
            ]);
        }

        $user = $this->findUserByPhone($firebasePhone);

        if (! $user) {
            if (! setting('registration_enabled', true)) {
                throw ValidationException::withMessages([
                    'mobile' => 'No account found for this mobile number, and registrations are currently closed.',
                ]);
            }

            $this->storeMobileRegistrationHandoff($request, $firebasePhone, $firebaseClaims);

            return response()->json([
                'redirect' => (bool) setting('auth_register_mobile_enabled', false)
                    ? route('register', ['auth_mode' => 'mobile'], false)
                    : route('register', [], false),
                'next' => 'register',
            ]);
        }

        if ($user->is_blocked) {
            throw ValidationException::withMessages([
                'mobile' => 'Your account has been blocked. Please contact support.',
            ]);
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        $user->forceFill([
            'last_seen_at' => now(),
        ])->save();

        $this->clearMobileRegistrationHandoff($request);

        return response()->json([
            'redirect' => route('dashboard', absolute: false),
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
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

    private function storeMobileRegistrationHandoff(Request $request, string $phone, array $firebaseClaims): void
    {
        $request->session()->put(self::MOBILE_REGISTRATION_HANDOFF_SESSION, [
            'phone' => $phone,
            'uid' => (string) ($firebaseClaims['uid'] ?? ''),
            'email' => (string) ($firebaseClaims['email'] ?? ''),
            'verified_at' => now()->getTimestamp(),
        ]);
    }

    private function clearMobileRegistrationHandoff(Request $request): void
    {
        $request->session()->forget(self::MOBILE_REGISTRATION_HANDOFF_SESSION);
    }
}
