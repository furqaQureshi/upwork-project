@php
    $registerEmailEnabled = (bool) setting('auth_register_email_enabled', true);
    $registerMobileEnabled = (bool) setting('auth_register_mobile_enabled', false);

    if (! $registerEmailEnabled && ! $registerMobileEnabled) {
        $registerEmailEnabled = true;
    }

    $mobileRegistrationHandoff = is_array($mobileRegistrationHandoff ?? null) ? $mobileRegistrationHandoff : null;
    $mobileRegistrationHandoffPhone = trim((string) ($mobileRegistrationHandoff['phone'] ?? ''));
    $hasMobileRegistrationHandoff = $mobileRegistrationHandoffPhone !== '';

    $requestedMode = (string) old('auth_mode', '');
    $initialMode = $hasMobileRegistrationHandoff && $registerMobileEnabled
        ? 'mobile'
        : (in_array($requestedMode, ['email', 'mobile'], true)
            ? $requestedMode
            : ($registerMobileEnabled && ! $registerEmailEnabled ? 'mobile' : 'email'));
@endphp

<x-guest-layout>
    @vite('resources/js/auth-phone.js')

    <div data-auth-mode-root data-auth-flow="register" data-mobile-endpoint="{{ route('register.firebase') }}" data-auth-initial-mode="{{ $initialMode }}" data-mobile-handoff-active="{{ $hasMobileRegistrationHandoff ? '1' : '0' }}" data-mobile-handoff-phone="{{ $mobileRegistrationHandoffPhone }}">
        <div class="mb-6">
            <h1 class="font-display text-2xl font-bold text-slate-900">Create your account</h1>
            <p class="mt-1 text-sm text-slate-600">Start posting listings and chatting with buyers instantly.</p>
        </div>

        @if ($hasMobileRegistrationHandoff)
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <p class="text-sm font-semibold text-emerald-800">Mobile number verified</p>
                <p class="mt-1 text-sm text-emerald-700">
                    @if ($registerMobileEnabled)
                        Continue with your remaining details below. You do not need to enter OTP again.
                    @else
                        Continue with the remaining account details below. Your verified mobile number has been prefilled.
                    @endif
                </p>
            </div>
        @endif

        @if ($registerEmailEnabled && $registerMobileEnabled)
            <div class="mb-4 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-slate-100 p-1">
                <button type="button" data-auth-mode-btn="email" class="rounded-lg px-3 py-2 text-sm font-semibold transition">Email</button>
                <button type="button" data-auth-mode-btn="mobile" class="rounded-lg px-3 py-2 text-sm font-semibold transition">Mobile OTP</button>
            </div>
        @endif

        @if ($registerEmailEnabled)
            <div data-auth-panel="email" class="{{ $initialMode === 'email' ? '' : 'hidden' }}">
                <form method="POST" action="{{ route('register') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="auth_mode" value="email">

                    <div>
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('Mobile number')" />
                        <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone" :value="old('phone', $mobileRegistrationHandoffPhone)" required autocomplete="tel" inputmode="numeric" maxlength="13" placeholder="9876543210" />
                        <p class="mt-1 text-xs text-slate-500">Required for all new accounts. Enter 10-digit mobile number; +91 is added automatically.</p>
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="city" :value="__('City')" />
                            <x-text-input id="city" class="block mt-1 w-full" type="text" name="city" :value="old('city')" autocomplete="address-level2" />
                            <x-input-error :messages="$errors->get('city')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="state" :value="__('State')" />
                            <x-text-input id="state" class="block mt-1 w-full" type="text" name="state" :value="old('state')" autocomplete="address-level1" />
                            <x-input-error :messages="$errors->get('state')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="password" :value="__('Password')" />

                        <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

                        <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <x-primary-button class="w-full justify-center">{{ __('Register') }}</x-primary-button>
                </form>
            </div>
        @endif

        @if ($registerMobileEnabled)
            <div data-auth-panel="mobile" class="space-y-4 {{ $initialMode === 'mobile' ? '' : 'hidden' }}">
                <div>
                    <x-input-label for="mobile_name" :value="__('Name')" />
                    <x-text-input id="mobile_name" type="text" class="block mt-1 w-full" data-mobile-name placeholder="Your full name" autocomplete="name" value="{{ old('name') }}" required />
                </div>

                <div>
                    <x-input-label for="mobile_phone" :value="__('Mobile number')" />
                    <x-text-input id="mobile_phone" type="text" class="block mt-1 w-full" data-mobile-phone autocomplete="tel" inputmode="numeric" maxlength="13" placeholder="9876543210" value="{{ old('phone', $mobileRegistrationHandoffPhone) }}" required @readonly($hasMobileRegistrationHandoff) />
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $hasMobileRegistrationHandoff ? 'This number was verified during login.' : 'Enter 10-digit mobile number. +91 prefix is added automatically.' }}
                    </p>
                </div>

                @if (! $hasMobileRegistrationHandoff)
                    <div>
                        <button type="button" data-send-otp class="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Send OTP
                        </button>
                    </div>

                    <div data-mobile-otp-section class="hidden space-y-3">
                        <div>
                            <x-input-label for="mobile_otp" :value="__('OTP code')" />
                            <x-text-input id="mobile_otp" type="text" class="block mt-1 w-full" data-mobile-otp inputmode="numeric" maxlength="6" placeholder="6-digit OTP" />
                        </div>

                        <button type="button" data-verify-otp class="inline-flex w-full items-center justify-center rounded-xl bg-orange-500 px-3 py-2 text-sm font-semibold text-white transition hover:bg-orange-600">
                            Verify &amp; register
                        </button>
                    </div>
                @else
                    <button type="button" data-verify-otp class="inline-flex w-full items-center justify-center rounded-xl bg-orange-500 px-3 py-2 text-sm font-semibold text-white transition hover:bg-orange-600">
                        Complete registration
                    </button>
                @endif

                <p data-mobile-status class="hidden rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700"></p>
                <p data-mobile-error class="hidden rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

                @if (! $hasMobileRegistrationHandoff)
                    <div data-firebase-recaptcha></div>
                @endif
            </div>
        @endif

        <p class="mt-5 text-center text-sm text-slate-600">
            Already have an account?
            <a class="font-semibold text-orange-600 hover:text-orange-700" href="{{ route('login') }}">
                {{ __('Log in') }}
            </a>
        </p>
    </div>
</x-guest-layout>
