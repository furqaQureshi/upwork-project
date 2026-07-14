<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl font-bold text-slate-900">Subscription Package</h1>
                <p class="text-sm text-slate-600">Check your current account level and manage upgrades.</p>
            </div>
            <a href="{{ route('subscriptions.plans') }}" class="app-btn-primary">Upgrade Your Account</a>
        </div>
    </x-slot>

    @php
        $statusBadgeClass = $hasActiveSubscription
            ? 'bg-emerald-100 text-emerald-700'
            : 'bg-slate-100 text-slate-700';
    @endphp

    <div class="mx-auto max-w-4xl space-y-4 pb-[calc(env(safe-area-inset-bottom)+5.75rem)] md:pb-0">
        <section class="app-card">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500">Account Status</p>
                    <h2 class="mt-1 font-display text-2xl font-bold text-slate-900">
                        {{ $hasActiveSubscription ? 'Subscribed User' : 'Regular User' }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-600">
                        @if ($hasActiveSubscription)
                            Your subscription is active. Enjoy premium posting and feature access.
                        @else
                            No subscription is active right now. Upgrade your account to unlock premium features.
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide {{ $statusBadgeClass }}">
                        {{ $hasActiveSubscription ? 'Active Subscription' : 'No Active Plan' }}
                    </span>

                    @if ($hasActiveSubscription && $userIsSellerVerified)
                        <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-orange-700">
                            <x-heroicon name="check-badge" class="h-4 w-4" />
                            Verified Seller
                        </span>
                    @endif
                </div>
            </div>

            @if ($primarySubscription)
                @php
                    $package = $primarySubscription->subscriptionPackage;
                @endphp
                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-slate-700">
                    <p class="font-semibold text-slate-900">Current plan: {{ $package?->name ?? 'Subscription plan' }}</p>
                    <p class="mt-1">Type: <span class="font-semibold">{{ ucfirst((string) ($package?->package_type ?? 'N/A')) }}</span></p>
                    <p class="mt-1">Amount: <span class="font-semibold">{{ $primarySubscription->currency }} {{ number_format((float) $primarySubscription->amount, 2) }}</span></p>
                    <p class="mt-1">Expires: <span class="font-semibold">{{ $primarySubscription->package_expires_at ? $primarySubscription->package_expires_at->format('d M Y, h:i A') : 'Never' }}</span></p>
                </div>
            @endif

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <a href="{{ route('subscriptions.plans') }}" class="app-btn-primary w-full justify-center">Subscribe Plan</a>
                <a href="{{ route('listings.index') }}" class="app-btn-muted w-full justify-center">Go to My Listings</a>
            </div>
        </section>

        <section class="app-card">
            <h3 class="font-display text-xl font-bold text-slate-900">Active Subscriptions</h3>
            <p class="mt-1 text-sm text-slate-600">A quick view of all currently active packages.</p>

            <div class="mt-4 space-y-2">
                @forelse ($activeSubscriptions as $purchase)
                    @php
                        $package = $purchase->subscriptionPackage;
                        $remainingItems = $package?->item_limit_type === 'unlimited'
                            ? 'Unlimited'
                            : (string) ((int) ($purchase->remaining_items ?? 0));
                        $remainingAi = $package?->ai_usage_limit_type === 'unlimited'
                            ? 'Unlimited'
                            : (string) ((int) ($purchase->remaining_ai_items ?? 0));
                    @endphp
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-slate-900">{{ $package?->name ?? 'Subscription package' }}</p>
                                <p class="text-xs uppercase tracking-wide text-slate-500">{{ ucfirst((string) ($package?->package_type ?? 'N/A')) }}</p>
                            </div>
                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-emerald-700">Active</span>
                        </div>

                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                            <p>Amount: <span class="font-semibold">{{ $purchase->currency }} {{ number_format((float) $purchase->amount, 2) }}</span></p>
                            <p>Remaining: <span class="font-semibold">{{ $remainingItems }}</span></p>
                            <p>AI: <span class="font-semibold">{{ $package?->allows_ai ? $remainingAi : 'N/A' }}</span></p>
                            <p>Expires: <span class="font-semibold">{{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y') : 'Never' }}</span></p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        No active subscription running.
                    </div>
                @endforelse
            </div>
        </section>

        <!-- Listing Subscriptions Section -->
        @if ($activeListingPurchases->isNotEmpty())
            <section class="app-card">
                <h3 class="font-display text-xl font-bold text-slate-900">Listing Packages</h3>
                <p class="mt-1 text-sm text-slate-600">Active listing subscription packages.</p>

                <div class="mt-4 space-y-2">
                    @foreach ($activeListingPurchases as $purchase)
                        @php
                            $package = $purchase->subscriptionPackage;
                            $remainingItems = $package?->item_limit_type === 'unlimited'
                                ? 'Unlimited'
                                : (string) ((int) ($purchase->remaining_items ?? 0));
                        @endphp
                        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-3 text-sm text-slate-700">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $package?->name ?? 'Listing package' }}</p>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Listing Package</p>
                                </div>
                                <span class="inline-flex rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-blue-700">Active</span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                <p>Amount: <span class="font-semibold">{{ $purchase->currency }} {{ number_format((float) $purchase->amount, 2) }}</span></p>
                                <p>Remaining Items: <span class="font-semibold">{{ $remainingItems }}</span></p>
                                <p>Expires: <span class="font-semibold">{{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y') : 'Never' }}</span></p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Featured Subscriptions Section -->
        @if ($activeFeaturedPurchases->isNotEmpty())
            <section class="app-card">
                <h3 class="font-display text-xl font-bold text-slate-900">Featured Packages</h3>
                <p class="mt-1 text-sm text-slate-600">Active featured listing subscription packages.</p>

                <div class="mt-4 space-y-2">
                    @foreach ($activeFeaturedPurchases as $purchase)
                        @php
                            $package = $purchase->subscriptionPackage;
                            $remainingItems = $package?->item_limit_type === 'unlimited'
                                ? 'Unlimited'
                                : (string) ((int) ($purchase->remaining_items ?? 0));
                        @endphp
                        <div class="rounded-2xl border border-purple-200 bg-purple-50 p-3 text-sm text-slate-700">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $package?->name ?? 'Featured package' }}</p>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Featured Package</p>
                                </div>
                                <span class="inline-flex rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-purple-700">Active</span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                <p>Amount: <span class="font-semibold">{{ $purchase->currency }} {{ number_format((float) $purchase->amount, 2) }}</span></p>
                                <p>Remaining Items: <span class="font-semibold">{{ $remainingItems }}</span></p>
                                <p>Expires: <span class="font-semibold">{{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y') : 'Never' }}</span></p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- Call Subscriptions Section -->
        @if ($activeCallPurchases->isNotEmpty())
            <section class="app-card">
                <h3 class="font-display text-xl font-bold text-slate-900">Call Packages</h3>
                <p class="mt-1 text-sm text-slate-600">Active call feature subscription packages.</p>

                <div class="mt-4 space-y-2">
                    @foreach ($activeCallPurchases as $purchase)
                        @php
                            $package = $purchase->subscriptionPackage;
                        @endphp
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-slate-700">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $package?->name ?? 'Call package' }}</p>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">Call Package</p>
                                </div>
                                <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-amber-700">Active</span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                <p>Amount: <span class="font-semibold">{{ $purchase->currency }} {{ number_format((float) $purchase->amount, 2) }}</span></p>
                                <p>Expires: <span class="font-semibold">{{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y') : 'Never' }}</span></p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <!-- AI Subscriptions Section -->
        @if ($activeAiPurchases->isNotEmpty())
            <section class="app-card">
                <h3 class="font-display text-xl font-bold text-slate-900">AI Packages</h3>
                <p class="mt-1 text-sm text-slate-600">Active AI feature subscription packages.</p>

                <div class="mt-4 space-y-2">
                    @foreach ($activeAiPurchases as $purchase)
                        @php
                            $package = $purchase->subscriptionPackage;
                            $remainingAi = $package?->ai_usage_limit_type === 'unlimited'
                                ? 'Unlimited'
                                : (string) ((int) ($purchase->remaining_ai_items ?? 0));
                        @endphp
                        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-3 text-sm text-slate-700">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $package?->name ?? 'AI package' }}</p>
                                    <p class="text-xs uppercase tracking-wide text-slate-500">AI Package</p>
                                </div>
                                <span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-indigo-700">Active</span>
                            </div>
                            <div class="mt-2 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
                                <p>Amount: <span class="font-semibold">{{ $purchase->currency }} {{ number_format((float) $purchase->amount, 2) }}</span></p>
                                <p>Remaining AI: <span class="font-semibold">{{ $remainingAi }}</span></p>
                                <p>Expires: <span class="font-semibold">{{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y') : 'Never' }}</span></p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-app-layout>
