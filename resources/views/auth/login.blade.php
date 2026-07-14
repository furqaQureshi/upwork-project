@php
    $loginEmailEnabled = (bool) setting('auth_login_email_enabled', true);
    $loginMobileEnabled = (bool) setting('auth_login_mobile_enabled', false);

    if (! $loginEmailEnabled && ! $loginMobileEnabled) {
        $loginEmailEnabled = true;
    }

    $requestedMode = (string) old('auth_mode', '');
    $initialMode = in_array($requestedMode, ['email', 'mobile'], true)
        ? $requestedMode
        : ($loginMobileEnabled && ! $loginEmailEnabled ? 'mobile' : 'email');
@endphp

<x-guest-layout>
    @vite('resources/js/auth-phone.js')

    <div data-auth-mode-root data-auth-flow="login" data-mobile-endpoint="{{ route('login.firebase') }}" data-auth-initial-mode="{{ $initialMode }}">
        <div class="mb-6">
            <h1 class="font-display text-2xl font-bold text-slate-900">Welcome back</h1>
            <p class="mt-1 text-sm text-slate-600">Log in to manage listings, chats, and favorites.</p>
        </div>

        <x-auth-session-status class="mb-4 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700" :status="session('status')" />

        @if ($loginEmailEnabled && $loginMobileEnabled)
            <div class="mb-4 grid grid-cols-2 gap-2 rounded-xl border border-slate-200 bg-slate-100 p-1">
                <button type="button" data-auth-mode-btn="email" class="rounded-lg px-3 py-2 text-sm font-semibold transition">Email</button>
                <button type="button" data-auth-mode-btn="mobile" class="rounded-lg px-3 py-2 text-sm font-semibold transition">Mobile OTP</button>
            </div>
        @endif

        @if ($loginEmailEnabled)
            <div data-auth-panel="email" class="{{ $initialMode === 'email' ? '' : 'hidden' }}">
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="auth_mode" value="email">

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password" :value="__('Password')" />

                        <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-between">
                        <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate-600">
                            <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-orange-500 focus:ring-orange-300" name="remember">
                            <span>{{ __('Remember me') }}</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a class="text-sm font-semibold text-orange-600 hover:text-orange-700" href="{{ route('password.request') }}">
                                {{ __('Forgot password?') }}
                            </a>
                        @endif
                    </div>

                    <x-primary-button class="w-full justify-center">
                        {{ __('Log in') }}
                    </x-primary-button>
                </form>
            </div>
        @endif

        @if ($loginMobileEnabled)
            <div data-auth-panel="mobile" class="space-y-4 {{ $initialMode === 'mobile' ? '' : 'hidden' }}">
                <div>
                    <x-input-label for="mobile_phone" :value="__('Mobile number')" />
                    <x-text-input id="mobile_phone" type="text" class="block mt-1 w-full" data-mobile-phone autocomplete="tel" inputmode="numeric" maxlength="13" placeholder="9876543210" />
                    <p class="mt-1 text-xs text-slate-500">Enter 10-digit mobile number. +91 prefix is added automatically.</p>
                </div>

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
                        Verify &amp; login
                    </button>
                </div>

                <p data-mobile-status class="hidden rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-700"></p>
                <p data-mobile-error class="hidden rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

                <div data-firebase-recaptcha></div>
            </div>
        @endif

        <p class="mt-5 text-center text-sm text-slate-600">
            New to Unsell?
            <a href="{{ route('register') }}" class="font-semibold text-orange-600 hover:text-orange-700">Create account</a>
        </p>
    </div>
</x-guest-layout>
