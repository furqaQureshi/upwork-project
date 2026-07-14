<section>
    <header>
        <h2 class="font-display text-xl font-bold text-slate-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="mt-2 text-sm text-slate-700">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="font-semibold text-orange-600 hover:text-orange-700">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-emerald-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="phone" :value="__('Phone')" />
            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $user->phone)" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="city" :value="__('City')" />
                <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $user->city)" autocomplete="address-level2" />
                <x-input-error class="mt-2" :messages="$errors->get('city')" />
            </div>

            <div>
                <x-input-label for="state" :value="__('State')" />
                <x-text-input id="state" name="state" type="text" class="mt-1 block w-full" :value="old('state', $user->state)" autocomplete="address-level1" />
                <x-input-error class="mt-2" :messages="$errors->get('state')" />
            </div>
        </div>

        @php
            $verificationStatus = $user->seller_verification_status ?: 'unsubmitted';
            $sellerVerificationEnabled = (bool) old(
                'apply_seller_verification',
                in_array($verificationStatus, ['pending', 'approved', 'rejected'], true) || (bool) $user->verification_document_path
            );
            $statusPalette = match ($verificationStatus) {
                'approved' => 'bg-emerald-100 text-emerald-700',
                'pending' => 'bg-amber-100 text-amber-700',
                'rejected' => 'bg-rose-100 text-rose-700',
                default => 'bg-slate-100 text-slate-700',
            };
        @endphp

        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4" x-data="{ enableSellerVerification: @js($sellerVerificationEnabled) }">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-display text-base font-bold text-slate-900">Seller Document Verification</h3>
                    <p class="mt-1 text-xs text-slate-600">Verified seller badge is granted only after admin approves your submitted document.</p>
                </div>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusPalette }}">
                    {{ ucfirst($verificationStatus) }}
                </span>
            </div>

            <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3">
                <input type="hidden" name="apply_seller_verification" value="0">
                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        name="apply_seller_verification"
                        value="1"
                        x-model="enableSellerVerification"
                        class="mt-1 h-4 w-4 rounded border-slate-300 text-orange-500 focus:ring-orange-400"
                    >
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">Apply for seller verification</span>
                        <span class="block text-xs text-slate-500">Enable this option to submit or update your document details.</span>
                    </span>
                </label>
                <x-input-error class="mt-2" :messages="$errors->get('apply_seller_verification')" />
            </div>

            <div class="mt-4 space-y-4" x-show="enableSellerVerification" x-cloak>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="verification_document_type" :value="__('Document Type')" />
                        <select id="verification_document_type" name="verification_document_type" class="app-select mt-1">
                            <option value="">Select type</option>
                            @foreach (['Aadhaar Card', 'PAN Card', 'Passport', 'Driving License', 'Voter ID', 'GST Certificate', 'Business Registration', 'Other'] as $docType)
                                <option value="{{ $docType }}" @selected(old('verification_document_type', $user->verification_document_type) === $docType)>{{ $docType }}</option>
                            @endforeach
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('verification_document_type')" />
                    </div>

                    <div>
                        <x-input-label for="verification_document_number" :value="__('Document Number')" />
                        <x-text-input id="verification_document_number" name="verification_document_number" type="text" class="mt-1 block w-full" :value="old('verification_document_number', $user->verification_document_number)" />
                        <x-input-error class="mt-2" :messages="$errors->get('verification_document_number')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="verification_document" :value="__('Upload Document (JPG, PNG, WEBP, PDF)')" />
                    <input id="verification_document" name="verification_document" type="file" class="app-input mt-1" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <x-input-error class="mt-2" :messages="$errors->get('verification_document')" />

                    @if ($user->verification_document_url)
                        <a href="{{ $user->verification_document_url }}" target="_blank" rel="noopener" class="mt-2 inline-flex text-xs font-semibold text-orange-600 underline">View current document</a>
                    @endif

                    @if ($user->seller_verification_note)
                        <p class="mt-2 text-xs font-semibold text-rose-600">Admin note: {{ $user->seller_verification_note }}</p>
                    @endif
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-500" x-show="!enableSellerVerification" x-cloak>
                Turn this on when you are ready to submit your document for seller verification.
            </p>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>

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
</section>
