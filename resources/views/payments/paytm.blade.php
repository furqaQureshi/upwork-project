<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-3xl font-bold text-slate-900">Paytm Checkout</h1>
    </x-slot>

    @php
        $currencySymbol = (string) setting('site_currency_symbol', 'Rs');
    @endphp

    <div class="mx-auto max-w-2xl space-y-4 pb-24 md:pb-0">

        {{-- Step 3 of 3 --}}
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="flex items-center gap-3 opacity-50">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-300 text-xs font-bold text-slate-700">1</span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Step 1</p>
                        <p class="text-sm font-semibold text-slate-700">Select Options</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 opacity-50">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-slate-300 text-xs font-bold text-slate-700">2</span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Step 2</p>
                        <p class="text-sm font-semibold text-slate-700">Review Total</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-orange-500 text-xs font-bold text-white">3</span>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Step 3</p>
                        <p class="text-sm font-semibold text-slate-900">Complete Payment</p>
                    </div>
                </div>
            </div>
            <div class="mt-3 h-1.5 rounded-full bg-slate-200">
                <div class="h-1.5 w-full rounded-full bg-orange-500"></div>
            </div>
        </div>

        {{-- Listing Details --}}
        <div class="app-card">
            <h3 class="mb-3 font-display text-base font-bold text-slate-900">Listing Being Featured</h3>
            <div class="flex items-start gap-3">
                <img src="{{ $listing->main_image_url }}" alt="{{ $listing->title }}" class="h-14 w-14 shrink-0 rounded-2xl object-cover">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-slate-900">{{ $listing->title }}</p>
                    <p class="text-sm text-slate-600">{{ $listing->currency }} {{ number_format((float) $listing->price) }}</p>
                    <p class="text-xs text-slate-500">{{ $listing->city }}</p>
                </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-orange-50 px-4 py-3 text-sm">
                <div>
                    <p class="text-xs text-slate-500">Duration</p>
                    <p class="font-semibold text-slate-900">{{ $payment->feature_days }} days</p>
                </div>
                <div>
                    <p class="text-xs text-slate-500">Amount Due</p>
                    <p class="font-semibold text-orange-600">{{ $currencySymbol }}{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</p>
                </div>
            </div>
        </div>

        {{-- Payment Action --}}
        <div class="app-card space-y-4 text-center">
            <h2 class="font-display text-xl font-bold text-slate-900">Redirecting to Paytm</h2>
            <p class="text-xs text-slate-500">You will be redirected to Paytm to complete the payment. If not redirected automatically, tap the button below.</p>
            <form id="paytm-checkout-form" method="POST" action="{{ $checkout['action_url'] }}">
                <input type="hidden" name="mid" value="{{ $checkout['mid'] }}">
                <input type="hidden" name="orderId" value="{{ $checkout['order_id'] }}">
                <input type="hidden" name="txnToken" value="{{ $checkout['txn_token'] }}">
                <div class="payment-desktop-flex items-center justify-center gap-3">
                    <button type="submit" class="app-btn-primary">Continue to Paytm</button>
                    <a href="{{ route('payments.checkout', $listing) }}" class="app-btn-muted">Back</a>
                </div>
            </form>
        </div>

    </div>

    {{-- Mobile fixed bottom payment bar --}}
    <div class="payment-mobile-only fixed inset-x-0 bottom-0 z-[80] border-t border-slate-200 bg-white/95 px-4 pb-[calc(env(safe-area-inset-bottom)+0.8rem)] pt-3 shadow-[0_-16px_35px_-20px_rgba(15,23,42,0.55)] backdrop-blur">
        <div class="mx-auto max-w-2xl">
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('payments.checkout', $listing) }}" class="app-btn-muted w-full">Back</a>
                <button type="submit" form="paytm-checkout-form" class="app-btn-primary w-full">Continue</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('paytm-checkout-form').submit();
        });
    </script>
</x-app-layout>
