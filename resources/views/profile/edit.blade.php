<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-display text-2xl font-bold text-slate-900">Profile Details</h2>
            <p class="text-sm text-slate-600">Manage your account information, security, and seller verification.</p>
        </div>
    </x-slot>

    @php
        $verificationStatus = $user->seller_verification_status ?: 'unsubmitted';
        $accountTypeLabel = $hasActiveSubscription ? 'Subscribed User' : 'Regular User';
        $accountTypePalette = $hasActiveSubscription
            ? 'bg-emerald-100 text-emerald-700'
            : 'bg-slate-100 text-slate-700';
        $profileVerification = $user->applicableSellerVerificationForCategory();
        $profilePackage = $profileVerification?->subscriptionPackage;
        $profileTier = (string) ($profilePackage?->seller_tier ?? '');
        $profileBadgeLabel = trim((string) ($profilePackage?->resolved_seller_badge_label ?? ''));
        if ($profileBadgeLabel === '') {
            $profileBadgeLabel = 'Verified';
        }
        $showVerifiedSellerBadge = $hasActiveSubscription && $profileVerification !== null;
        $avatarInitial = strtoupper(substr(trim((string) $user->name) ?: 'U', 0, 1));
        $isEmailVerified = method_exists($user, 'hasVerifiedEmail')
            ? $user->hasVerifiedEmail()
            : ($user->email_verified_at !== null);
        $locationLine = collect([$user->city, $user->state])
            ->map(static fn ($value): string => trim((string) $value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->implode(', ');
    @endphp

    <div class="mx-auto max-w-4xl space-y-4 pb-[calc(env(safe-area-inset-bottom)+5.75rem)] md:pb-0">
        @if (session('status') === 'profile-updated')
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                Profile updated successfully.
            </div>
        @endif

        <section class="app-card">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-orange-100 text-lg font-black text-orange-600">
                        {{ $avatarInitial }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-display text-lg font-bold text-slate-900">{{ $user->name }}</p>
                        <p class="truncate text-sm text-slate-600">{{ $user->email }}</p>
                        @if ($locationLine !== '')
                            <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $locationLine }}</p>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $accountTypePalette }}">
                        {{ $accountTypeLabel }}
                    </span>

                    {{-- Car Seller Verification Badge --}}
                    <x-verified-car-seller-badge :user="$user" size="md" />

                    {{-- Fallback Generic Badge --}}
                    @if ($showVerifiedSellerBadge && $profileTier !== 'car_verified')
                        <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-orange-700">
                            <x-heroicon name="check-badge" class="h-4 w-4" />
                            {{ $profileBadgeLabel }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Phone</p>
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $user->phone ?: 'Not set' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Email</p>
                    <p class="text-sm font-semibold {{ $isEmailVerified ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ $isEmailVerified ? 'Verified' : 'Unverified' }}
                    </p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Member Since</p>
                    <p class="text-sm font-semibold text-slate-900">{{ $user->created_at?->format('M Y') }}</p>
                </div>
                <div class="rounded-2xl bg-slate-50 px-3 py-2">
                    <p class="text-[11px] uppercase tracking-wide text-slate-500">Account Type</p>
                    <p class="text-sm font-semibold text-slate-900">{{ $accountTypeLabel }}</p>
                </div>
            </div>

            @if ($hasActiveSubscription && $activeSubscription)
                @php
                    $activePackage = $activeSubscription->subscriptionPackage;
                @endphp
                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-slate-700">
                    <p class="font-semibold text-slate-900">Current subscription: {{ $activePackage?->name ?? 'Active plan' }}</p>
                    <p class="mt-1">Type: <span class="font-semibold">{{ ucfirst((string) ($activePackage?->package_type ?? 'N/A')) }}</span></p>
                    <p class="mt-1">Expires: <span class="font-semibold">{{ $activeSubscription->package_expires_at ? $activeSubscription->package_expires_at->format('d M Y, h:i A') : 'Never' }}</span></p>
                </div>
            @endif

            <div class="mt-4 grid gap-2 sm:grid-cols-2 sm:gap-3">
                <a href="{{ route('profile.edit-form') }}" class="app-btn-primary w-full justify-center">Edit Profile</a>
                <a href="{{ route('subscriptions.plans') }}" class="app-btn-muted w-full justify-center">Upgrade Account</a>
            </div>
        </section>

        <section class="app-card">
            <h3 class="font-display text-lg font-bold text-slate-900">Seller Verification</h3>
            <p class="mt-1 text-sm text-slate-600">Manage your seller verification details from Edit Profile.</p>

            <div class="mt-4 space-y-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm">
                <p><span class="font-semibold text-slate-900">Status:</span> <span class="text-slate-700">{{ ucfirst($verificationStatus) }}</span></p>
                <p><span class="font-semibold text-slate-900">Document Type:</span> <span class="text-slate-700">{{ $user->verification_document_type ?: 'Not submitted' }}</span></p>
                <p><span class="font-semibold text-slate-900">Document Number:</span> <span class="text-slate-700">{{ $user->verification_document_number ?: 'Not submitted' }}</span></p>

                @if ($user->verification_document_url)
                    <a href="{{ $user->verification_document_url }}" target="_blank" rel="noopener" class="inline-flex text-xs font-semibold text-orange-600 underline">View Uploaded Document</a>
                @endif

                @if ($user->seller_verification_note)
                    <p class="text-xs font-semibold text-rose-600">Admin note: {{ $user->seller_verification_note }}</p>
                @endif
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="app-card" x-data="soundPrefs()">
                <h3 class="font-display text-base font-semibold text-slate-900">Notification Preferences</h3>
                <p class="mt-1 text-sm text-slate-500">Control in-app alerts for this device.</p>

                <div class="mt-4 flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Message sound</p>
                        <p class="text-xs text-slate-500">Play a short chime when a new message arrives</p>
                    </div>
                    <button
                        type="button"
                        @click="toggle()"
                        :aria-checked="enabled ? 'true' : 'false'"
                        role="switch"
                        class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                        :class="enabled ? 'bg-orange-500' : 'bg-slate-300'"
                    >
                        <span
                            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                        ></span>
                    </button>
                </div>

                <p x-show="saved" x-transition class="mt-2 text-xs font-semibold text-emerald-600">Preference saved.</p>
            </section>

            <section class="app-card">
                <h3 class="font-display text-base font-semibold text-slate-900">Account Actions</h3>
                <p class="mt-1 text-sm text-slate-500">Update your account details, password, or seller document info.</p>
                <a href="{{ route('profile.edit-form') }}" class="mt-4 app-btn-muted w-full justify-center">Go to Edit Profile Form</a>
            </section>
        </div>
    </div>

    @if (session('status') === 'profile-updated')
        @php
            $profileLocationLabel = collect([$user->city, $user->state])
                ->map(static fn ($value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->implode(', ');
        @endphp
        <script>
            (() => {
                const label = @js($profileLocationLabel);

                try {
                    window.localStorage.setItem('unsell_location_state', JSON.stringify({
                        label,
                        promptHandled: true,
                    }));
                    window.localStorage.removeItem('unsell_selected_location_label');
                    window.localStorage.removeItem('unsell_location_prompt_seen');
                } catch (_) {
                }

                window.dispatchEvent(new CustomEvent('unsell-location-updated', {
                    detail: { label },
                }));
            })();
        </script>
    @endif

    <script>
        function soundPrefs() {
            return {
                enabled: localStorage.getItem('unsell_sound_enabled') !== 'false',
                saved: false,
                toggle() {
                    this.enabled = !this.enabled;
                    localStorage.setItem('unsell_sound_enabled', this.enabled ? 'true' : 'false');
                    if (typeof window.unisellSetSoundEnabled === 'function') {
                        window.unisellSetSoundEnabled(this.enabled);
                    }
                    this.saved = true;
                    setTimeout(() => { this.saved = false; }, 1800);
                },
            };
        }
    </script>
</x-app-layout>
