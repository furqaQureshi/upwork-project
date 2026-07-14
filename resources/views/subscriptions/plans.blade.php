@php
    $callFeatureRequested = request('feature') === 'call';
    $aiFeatureRequested = request('feature') === 'ai';
    $showGatewayChooser = (bool) ($showGatewayOptions ?? false);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl font-bold text-slate-900">All Subscription Plans</h1>
                <p class="text-sm text-slate-600">Choose a plan and complete payment to upgrade your account.</p>
            </div>
            <a href="{{ route('subscriptions.index') }}" class="app-btn-muted">Back to Subscription</a>
        </div>
    </x-slot>

    <div class="space-y-5 pb-[calc(env(safe-area-inset-bottom)+6rem)] md:pb-0">
        @if ($callFeatureRequested)
            <section class="app-card border-orange-200 bg-orange-50/70">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-orange-500 text-white">
                        <x-heroicon name="phone" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">Unlock seller calling</h2>
                        <p class="mt-1 text-sm text-slate-600">Choose a package with Call Access enabled to call sellers directly from listing cards and detail pages.</p>
                    </div>
                </div>
            </section>
        @endif

        @if ($aiFeatureRequested)
            <section class="app-card border-cyan-200 bg-cyan-50/70">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-cyan-500 text-white">
                        <x-heroicon name="check-badge" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="font-display text-lg font-bold text-slate-900">Unlock AI tools</h2>
                        <p class="mt-1 text-sm text-slate-600">Choose a package with AI Access enabled to use listing assistant, smart pricing, CompassGPT, and CV matching.</p>
                    </div>
                </div>
            </section>
        @endif

        <section class="app-card border-slate-200 bg-slate-50/80">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-xs font-bold text-white">1</span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Step 1</p>
                        <p class="text-sm font-semibold text-slate-900">Choose Package</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 opacity-85">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-300 text-xs font-bold text-slate-700">2</span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Step 2</p>
                        <p class="text-sm font-semibold text-slate-900">Confirm Gateway</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 opacity-70">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-700">3</span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Step 3</p>
                        <p class="text-sm font-semibold text-slate-900">Complete Payment</p>
                    </div>
                </div>
            </div>
            <div class="mt-3 h-1.5 rounded-full bg-slate-200">
                <div class="h-1.5 w-1/3 rounded-full bg-orange-500"></div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-4">
            <div class="app-card">
                <h2 class="font-display text-xl font-bold text-slate-900">Active Listing Packages</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($activeListingPurchases as $purchase)
                        @php
                            $package = $purchase->subscriptionPackage;
                            $remaining = $package?->item_limit_type === 'unlimited'
                                ? 'Unlimited'
                                : (string) ((int) ($purchase->remaining_items ?? 0));
                            $aiRemaining = $package?->ai_usage_limit_type === 'unlimited'
                                ? 'Unlimited'
                                : (string) ((int) ($purchase->remaining_ai_items ?? 0));
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                            <p class="font-semibold text-slate-900">{{ $package?->name }}</p>
                            @if ($package?->allows_call)
                                <p class="mt-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-orange-700">
                                    <x-heroicon name="phone" class="h-3.5 w-3.5" />
                                    Call access active
                                </p>
                            @endif
                            @if ($package?->allows_ai)
                                <p class="mt-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-cyan-700">
                                    <x-heroicon name="check-badge" class="h-3.5 w-3.5" />
                                    AI access active
                                </p>
                                <p>AI remaining: <span class="font-semibold">{{ $aiRemaining }}</span></p>
                            @endif
                            <p>Remaining: <span class="font-semibold">{{ $remaining }}</span></p>
                            <p class="text-xs text-slate-500">
                                Expires:
                                {{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y, h:i A') : 'Never' }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">No active listing package found.</p>
                    @endforelse
                </div>
            </div>

            <div class="app-card">
                <h2 class="font-display text-xl font-bold text-slate-900">Active Featured Packages</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($activeFeaturedPurchases as $purchase)
                        @php
                            $package = $purchase->subscriptionPackage;
                            $remaining = $package?->item_limit_type === 'unlimited'
                                ? 'Unlimited'
                                : (string) ((int) ($purchase->remaining_items ?? 0));
                            $aiRemaining = $package?->ai_usage_limit_type === 'unlimited'
                                ? 'Unlimited'
                                : (string) ((int) ($purchase->remaining_ai_items ?? 0));
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                            <p class="font-semibold text-slate-900">{{ $package?->name }}</p>
                            @if ($package?->allows_call)
                                <p class="mt-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-orange-700">
                                    <x-heroicon name="phone" class="h-3.5 w-3.5" />
                                    Call access active
                                </p>
                            @endif
                            @if ($package?->allows_ai)
                                <p class="mt-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-cyan-700">
                                    <x-heroicon name="check-badge" class="h-3.5 w-3.5" />
                                    AI access active
                                </p>
                                <p>AI remaining: <span class="font-semibold">{{ $aiRemaining }}</span></p>
                            @endif
                            <p>Remaining boosts: <span class="font-semibold">{{ $remaining }}</span></p>
                            <p class="text-xs text-slate-500">
                                Expires:
                                {{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y, h:i A') : 'Never' }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">No active featured package found.</p>
                    @endforelse
                </div>
            </div>

            <div class="app-card">
                <h2 class="font-display text-xl font-bold text-slate-900">Active Call Access</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($activeCallPurchases as $purchase)
                        <div class="rounded-2xl border border-orange-200 bg-orange-50 p-3 text-sm text-slate-700">
                            <p class="font-semibold text-slate-900">{{ $purchase->subscriptionPackage?->name }}</p>
                            <p class="mt-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-orange-700">
                                <x-heroicon name="phone" class="h-3.5 w-3.5" />
                                Seller calling enabled
                            </p>
                            <p class="text-xs text-slate-500">
                                Expires:
                                {{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y, h:i A') : 'Never' }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">No active call access found.</p>
                    @endforelse
                </div>
            </div>

            <div class="app-card">
                <h2 class="font-display text-xl font-bold text-slate-900">Active AI Access</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($activeAiPurchases as $purchase)
                        @php
                            $package = $purchase->subscriptionPackage;
                            $aiRemaining = $package?->ai_usage_limit_type === 'unlimited'
                                ? 'Unlimited'
                                : (string) ((int) ($purchase->remaining_ai_items ?? 0));
                        @endphp
                        <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-3 text-sm text-slate-700">
                            <p class="font-semibold text-slate-900">{{ $package?->name }}</p>
                            <p class="mt-1 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-cyan-700">
                                <x-heroicon name="check-badge" class="h-3.5 w-3.5" />
                                AI tools enabled
                            </p>
                            <p>AI remaining: <span class="font-semibold">{{ $aiRemaining }}</span></p>
                            <p class="text-xs text-slate-500">
                                Expires:
                                {{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y, h:i A') : 'Never' }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">No active AI access found.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-2xl font-bold text-slate-900">Listing Packages</h2>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($listingPackages as $package)
                    <div class="app-card space-y-3 {{ $package->allows_call || $package->allows_ai ? 'border-orange-200 shadow-orange-100/40' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-orange-600">Listing</p>
                                <h3 class="font-display text-lg font-bold text-slate-900">{{ $package->name }}</h3>
                            </div>
                            @if ($package->icon_url)
                                <img src="{{ $package->icon_url }}" alt="{{ $package->name }}" class="h-11 w-11 rounded-xl object-cover">
                            @else
                                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                    <x-heroicon name="clipboard-document-list" class="h-5 w-5" />
                                </span>
                            @endif
                        </div>

                        <p class="text-2xl font-bold text-slate-900">{{ $currencySymbol }}{{ number_format((float) $package->final_price, 2) }}</p>

                        @if ($package->allows_call)
                            <p class="inline-flex items-center gap-2 rounded-full bg-orange-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-orange-700">
                                <x-heroicon name="phone" class="h-4 w-4" />
                                Call Access Included
                            </p>
                        @endif

                        @if ($package->allows_ai)
                            <p class="inline-flex items-center gap-2 rounded-full bg-cyan-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-cyan-700">
                                <x-heroicon name="check-badge" class="h-4 w-4" />
                                AI Access: {{ $package->ai_usage_limit_label }}
                            </p>
                        @endif

                        <div class="space-y-1 text-sm text-slate-600">
                            <p>Package valid: <span class="font-semibold text-slate-800">{{ $package->package_duration_label }}</span></p>
                            <p>Listing quota: <span class="font-semibold text-slate-800">{{ $package->item_limit_label }}</span></p>
                            <p>Listing duration: <span class="font-semibold text-slate-800">{{ $package->listing_duration_label }}</span></p>
                            <p>AI quota: <span class="font-semibold text-slate-800">{{ $package->allows_ai ? $package->ai_usage_limit_label : 'Not included' }}</span></p>
                            <p>
                                Category scope:
                                <span class="font-semibold text-slate-800">
                                    {{ $package->category_scope === 'global' ? 'All categories' : ($package->category?->display_name ?? 'Specific category') }}
                                </span>
                            </p>
                        </div>

                        @if (! empty($package->key_points))
                            <ul class="space-y-1 text-sm text-slate-600">
                                @foreach ($package->key_points as $point)
                                    <li class="flex items-start gap-2">
                                        <x-heroicon name="check-badge" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                        <span>{{ $point }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('subscriptions.initialize', $package) }}" class="space-y-2">
                            @csrf
                            @if ($showGatewayChooser)
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Gateway</label>
                                <select name="gateway" class="app-select" required>
                                    @foreach ($gateways as $gatewayValue => $gatewayLabel)
                                        <option value="{{ $gatewayValue }}" @selected($defaultGateway === $gatewayValue)>{{ $gatewayLabel }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="app-btn-primary w-full text-sm">Continue to Payment</button>
                            @else
                                <input type="hidden" name="gateway" value="{{ $defaultGateway }}">
                                <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                                    {{ $inAppPayment ? 'In-app payment: ' : 'Gateway: ' }}{{ $gateways[$defaultGateway] ?? 'Razorpay' }}
                                </p>
                                <button type="submit" class="app-btn-primary w-full text-sm">{{ $inAppPayment ? 'Continue to In-App Payment' : 'Continue to Payment' }}</button>
                            @endif
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-600">No listing package is available right now.</p>
                @endforelse
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="font-display text-2xl font-bold text-slate-900">Featured Packages</h2>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($featuredPackages as $package)
                    <div class="app-card space-y-3 {{ $package->allows_call || $package->allows_ai ? 'border-orange-200 shadow-orange-100/40' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Featured</p>
                                <h3 class="font-display text-lg font-bold text-slate-900">{{ $package->name }}</h3>
                            </div>
                            @if ($package->icon_url)
                                <img src="{{ $package->icon_url }}" alt="{{ $package->name }}" class="h-11 w-11 rounded-xl object-cover">
                            @else
                                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                                    <x-heroicon name="tag" class="h-5 w-5" />
                                </span>
                            @endif
                        </div>

                        <p class="text-2xl font-bold text-slate-900">{{ $currencySymbol }}{{ number_format((float) $package->final_price, 2) }}</p>

                        @if ($package->allows_call)
                            <p class="inline-flex items-center gap-2 rounded-full bg-orange-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-orange-700">
                                <x-heroicon name="phone" class="h-4 w-4" />
                                Call Access Included
                            </p>
                        @endif

                        @if ($package->allows_ai)
                            <p class="inline-flex items-center gap-2 rounded-full bg-cyan-50 px-3 py-1 text-xs font-bold uppercase tracking-wide text-cyan-700">
                                <x-heroicon name="check-badge" class="h-4 w-4" />
                                AI Access: {{ $package->ai_usage_limit_label }}
                            </p>
                        @endif

                        <div class="space-y-1 text-sm text-slate-600">
                            <p>Package valid: <span class="font-semibold text-slate-800">{{ $package->package_duration_label }}</span></p>
                            <p>Featured quota: <span class="font-semibold text-slate-800">{{ $package->item_limit_label }}</span></p>
                            <p>Featured duration: <span class="font-semibold text-slate-800">{{ $package->listing_duration_label }}</span></p>
                            <p>AI quota: <span class="font-semibold text-slate-800">{{ $package->allows_ai ? $package->ai_usage_limit_label : 'Not included' }}</span></p>
                            <p>Category scope: <span class="font-semibold text-slate-800">All categories</span></p>
                        </div>

                        @if (! empty($package->key_points))
                            <ul class="space-y-1 text-sm text-slate-600">
                                @foreach ($package->key_points as $point)
                                    <li class="flex items-start gap-2">
                                        <x-heroicon name="check-badge" class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                        <span>{{ $point }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('subscriptions.initialize', $package) }}" class="space-y-2">
                            @csrf
                            @if ($showGatewayChooser)
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Gateway</label>
                                <select name="gateway" class="app-select" required>
                                    @foreach ($gateways as $gatewayValue => $gatewayLabel)
                                        <option value="{{ $gatewayValue }}" @selected($defaultGateway === $gatewayValue)>{{ $gatewayLabel }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="app-btn-primary w-full text-sm">Continue to Payment</button>
                            @else
                                <input type="hidden" name="gateway" value="{{ $defaultGateway }}">
                                <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700">
                                    {{ $inAppPayment ? 'In-app payment: ' : 'Gateway: ' }}{{ $gateways[$defaultGateway] ?? 'Razorpay' }}
                                </p>
                                <button type="submit" class="app-btn-primary w-full text-sm">{{ $inAppPayment ? 'Continue to In-App Payment' : 'Continue to Payment' }}</button>
                            @endif
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-slate-600">No featured package is available right now.</p>
                @endforelse
            </div>
        </section>

        <section class="app-card">
            <h2 class="font-display text-xl font-bold text-slate-900">Recent Purchases</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2">Package</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Remaining</th>
                            <th class="px-3 py-2">AI Remaining</th>
                            <th class="px-3 py-2">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPurchases as $purchase)
                            @php
                                $package = $purchase->subscriptionPackage;
                                $remaining = $package?->item_limit_type === 'unlimited'
                                    ? 'Unlimited'
                                    : (string) ((int) ($purchase->remaining_items ?? 0));
                                $aiRemaining = $package?->allows_ai
                                    ? ($package?->ai_usage_limit_type === 'unlimited'
                                        ? 'Unlimited'
                                        : (string) ((int) ($purchase->remaining_ai_items ?? 0)))
                                    : 'N/A';
                            @endphp
                            <tr class="border-t border-slate-200 text-slate-700">
                                <td class="px-3 py-2 font-semibold text-slate-900">{{ $package?->name }}</td>
                                <td class="px-3 py-2">{{ ucfirst($package?->package_type ?? 'N/A') }}</td>
                                <td class="px-3 py-2">{{ $purchase->currency }} {{ number_format((float) $purchase->amount, 2) }}</td>
                                <td class="px-3 py-2">{{ ucfirst($purchase->status) }}</td>
                                <td class="px-3 py-2">{{ $remaining }}</td>
                                <td class="px-3 py-2">{{ $aiRemaining }}</td>
                                <td class="px-3 py-2">{{ $purchase->created_at->format('d M Y, h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-4 text-sm text-slate-600">No package purchases yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
