<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl font-bold text-slate-900">Boost as Featured</h1>
                <p class="text-sm text-slate-600">Promote your listing at the top for more buyer visibility.</p>
            </div>
                  <a href="{{ route('listings.show', $listing) }}" class="app-btn-muted payment-desktop-inline">Back to Listing</a>
        </div>
    </x-slot>

    @php
        $isFeatureActive = $listing->is_featured && (! $listing->featured_until || $listing->featured_until->isFuture());
    @endphp

    <div class="grid gap-5 lg:grid-cols-3">
           <section class="order-2 space-y-4 lg:order-1 lg:col-span-2">
            @if ($isFeatureActive)
                <div class="app-card border-emerald-200 bg-emerald-50">
                    <p class="text-sm font-semibold text-emerald-700">
                        This listing is already featured
                        @if ($listing->featured_until)
                            until {{ $listing->featured_until->format('d M Y, h:i A') }}.
                        @else
                            with admin priority.
                        @endif
                    </p>
                </div>
            @endif

                <form id="featured-checkout-form" method="POST" action="{{ route('payments.initialize', $listing) }}" class="app-card space-y-5 pb-28 md:pb-0">
                @csrf

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-xs font-bold text-white">1</span>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Step 1</p>
                                <p class="text-sm font-semibold text-slate-900">Select Options</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 opacity-85">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-300 text-xs font-bold text-slate-700">2</span>
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Step 2</p>
                                <p class="text-sm font-semibold text-slate-900">Review Total</p>
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
                </div>

                @if ($usingPackageFlow)
                    <div class="space-y-3">
                        <h2 class="font-display text-xl font-bold text-slate-900">Use Active Featured Package</h2>
                        <p class="text-sm text-slate-600">This listing will be boosted by consuming one item from your active featured package.</p>

                        <div class="space-y-2">
                            @foreach ($activeFeaturedPurchases as $purchase)
                                @php
                                    $package = $purchase->subscriptionPackage;
                                    $remaining = $package?->item_limit_type === 'unlimited'
                                        ? 'Unlimited'
                                        : (string) ((int) ($purchase->remaining_items ?? 0));
                                    $durationDays = $package?->listing_duration_type === 'custom'
                                        ? (int) ($package?->listing_duration_days ?? 30)
                                        : 30;
                                @endphp
                                <div class="rounded-2xl border border-slate-200 bg-white/85 p-3 text-sm text-slate-700">
                                    <p class="font-semibold text-slate-900">{{ $package?->name }}</p>
                                    <p>Remaining boosts: <span class="font-semibold">{{ $remaining }}</span></p>
                                    <p>Boost duration per use: <span class="font-semibold">{{ $durationDays }} day(s)</span></p>
                                    <p class="text-xs text-slate-500">
                                        Expires:
                                        {{ $purchase->package_expires_at ? $purchase->package_expires_at->format('d M Y, h:i A') : 'Never' }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <button type="submit" class="app-btn-primary payment-desktop-inline w-full sm:w-auto">Boost With Package</button>
                @else
                    @if ($showGatewayOptions)
                        <div class="space-y-3">
                            <h2 class="font-display text-xl font-bold text-slate-900">1. Select Payment Gateway</h2>
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach ($gateways as $gatewayValue => $gatewayLabel)
                                    <label class="rounded-2xl border border-slate-200 bg-white/85 p-3 text-sm font-semibold text-slate-700">
                                        <input
                                            type="radio"
                                            name="gateway"
                                            value="{{ $gatewayValue }}"
                                            class="mr-2"
                                            @checked(old('gateway', $defaultGateway ?? 'razorpay') === $gatewayValue)
                                        >
                                        {{ $gatewayLabel }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('gateway')" class="mt-2" />
                        </div>
                    @else
                        <input type="hidden" name="gateway" value="{{ $defaultGateway }}">
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                            <p class="font-semibold">{{ $inAppPayment ? 'In-app payment enabled (all devices)' : 'Single gateway checkout' }}</p>
                            <p class="mt-1">Checkout will continue using {{ $gateways[$defaultGateway] ?? 'Razorpay' }} after duration selection.</p>
                        </div>
                    @endif

                    <div class="space-y-3">
                        <h2 class="font-display text-xl font-bold text-slate-900">{{ $showGatewayOptions ? '2. Choose Duration' : '1. Choose Duration' }}</h2>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($allowedDays as $days)
                                @php
                                    $optionAmount = $dailyRate * (int) $days;
                                @endphp
                                <label class="rounded-2xl border border-slate-200 bg-white/85 p-3 text-sm text-slate-700">
                                    <input
                                        type="radio"
                                        name="feature_days"
                                        value="{{ $days }}"
                                        class="mr-2"
                                        @checked((int) old('feature_days', 7) === (int) $days)
                                    >
                                    <span class="font-semibold">{{ $days }} days</span>
                                    <span class="ml-1 text-xs text-slate-500">({{ $currencySymbol }}{{ number_format($optionAmount, 2) }} {{ $currency }})</span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('feature_days')" class="mt-2" />
                    </div>

                    <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                        <p>Daily rate: <span class="font-semibold">{{ $currencySymbol }}{{ number_format($dailyRate, 2) }} {{ $currency }}</span></p>
                        <p class="mt-1 text-xs text-slate-500">Final amount is calculated at checkout based on selected duration.</p>
                    </div>

                    <button type="submit" class="app-btn-primary payment-desktop-inline w-full sm:w-auto">{{ $inAppPayment ? 'Continue to In-App Payment' : 'Continue to Payment' }}</button>
                @endif
            </form>
                @php
                    $mobileSubmitLabel = $usingPackageFlow
                        ? 'Boost With Package'
                        : ($inAppPayment ? 'Continue to In-App Payment' : 'Continue to Payment');
                @endphp
                <div class="payment-mobile-only fixed inset-x-0 bottom-0 z-[75] border-t border-slate-200 bg-white/95 px-4 pb-[calc(env(safe-area-inset-bottom)+0.8rem)] pt-3 shadow-[0_-16px_35px_-20px_rgba(15,23,42,0.55)] backdrop-blur">
                    <div class="mx-auto max-w-7xl">
                        <div class="grid grid-cols-2 gap-3">
                            <a href="{{ route('listings.show', $listing) }}" class="app-btn-muted w-full">Back</a>
                            <button type="submit" form="featured-checkout-form" class="app-btn-primary w-full">{{ $mobileSubmitLabel }}</button>
                        </div>
                    </div>
                </div>
            </section>

        <aside class="order-1 space-y-4 lg:order-2">
            <div class="app-card">
                <h3 class="font-display text-lg font-bold text-slate-900">Listing Preview</h3>
                <img src="{{ $listing->main_image_url }}" alt="{{ $listing->title }}" class="mt-3 h-40 w-full rounded-2xl object-cover">
                <p class="mt-3 font-semibold text-slate-900">{{ $listing->title }}</p>
                <p class="text-sm text-slate-600">{{ $listing->currency }} {{ number_format((float) $listing->price) }}</p>
                <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">{{ $listing->city }}</p>
            </div>

            <div class="app-card">
                <h3 class="font-display text-lg font-bold text-slate-900">Recent Promotions</h3>
                <div class="mt-3 space-y-2">
                    @forelse ($recentPayments as $recentPayment)
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $recentPayment->gateway }}</p>
                            <p class="text-sm font-semibold text-slate-900">{{ $recentPayment->currency }} {{ number_format((float) $recentPayment->amount, 2) }} • {{ $recentPayment->feature_days }} days</p>
                            <p class="text-xs text-slate-500">{{ ucfirst($recentPayment->status) }} • {{ $recentPayment->created_at->diffForHumans() }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">No featured payments yet.</p>
                    @endforelse
                </div>
            </div>
        </aside>
    </div>
</x-app-layout>
