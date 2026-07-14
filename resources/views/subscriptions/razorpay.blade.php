<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display text-3xl font-bold text-slate-900">Razorpay Checkout</h1>
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
                        <p class="text-sm font-semibold text-slate-700">Select Package</p>
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

        {{-- Package Details --}}
        <div class="app-card">
            <h3 class="mb-3 font-display text-base font-bold text-slate-900">Package Being Activated</h3>
            <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm">
                <p class="font-semibold text-slate-900">{{ $subscriptionPackage->name }}</p>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-orange-50 px-4 py-3 text-sm">
                <div>
                    <p class="text-xs text-slate-500">Amount Due</p>
                    <p class="font-semibold text-orange-600">{{ $currencySymbol }}{{ number_format((float) $purchase->amount, 2) }} {{ $purchase->currency }}</p>
                </div>
            </div>
        </div>

        {{-- Payment Action --}}
        <div class="app-card space-y-4 text-center">
            <h2 class="font-display text-xl font-bold text-slate-900">Complete Your Payment</h2>
            <p class="text-xs text-slate-500">The Razorpay payment window will open automatically. If it does not, tap the button below.</p>
            <div class="payment-desktop-flex items-center justify-center gap-3">
                <button type="button" data-open-razorpay class="app-btn-primary">Pay with Razorpay</button>
                <a href="{{ route('subscriptions.index') }}" class="app-btn-muted">Back</a>
            </div>
        </div>

    </div>

    {{-- Mobile fixed bottom payment bar --}}
    <div class="payment-mobile-only fixed inset-x-0 bottom-0 z-[80] border-t border-slate-200 bg-white/95 px-4 pb-[calc(env(safe-area-inset-bottom)+0.8rem)] pt-3 shadow-[0_-16px_35px_-20px_rgba(15,23,42,0.55)] backdrop-blur">
        <div class="mx-auto max-w-2xl">
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('subscriptions.index') }}" class="app-btn-muted w-full">Back</a>
                <button type="button" data-open-razorpay class="app-btn-primary w-full">Pay Now</button>
            </div>
        </div>
    </div>

    <form id="razorpay-callback" method="POST" action="{{ route('subscriptions.callback.razorpay') }}" class="hidden">
        @csrf
        <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    </form>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const callbackForm = document.getElementById('razorpay-callback');
            const openButtons = document.querySelectorAll('[data-open-razorpay]');

            const options = {
                key: @json($checkout['key_id']),
                amount: @json((int) round(((float) $purchase->amount) * 100)),
                currency: @json($purchase->currency),
                name: @json(config('app.name')),
                description: @json('Package purchase: '.$subscriptionPackage->name),
                order_id: @json($checkout['order_id']),
                prefill: {
                    name: @json(auth()->user()->name),
                    email: @json(auth()->user()->email),
                },
                theme: {
                    color: '#ea580c'
                },
                modal: {
                    ondismiss: function () {
                        window.location.href = @json(route('subscriptions.index'));
                    }
                },
                handler: function (response) {
                    document.getElementById('razorpay_order_id').value = response.razorpay_order_id;
                    document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                    document.getElementById('razorpay_signature').value = response.razorpay_signature;
                    callbackForm.submit();
                }
            };

            const razorpay = new window.Razorpay(options);
            razorpay.on('payment.failed', function () {
                window.location.href = @json(route('subscriptions.index'));
            });

            openButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    razorpay.open();
                });
            });

            razorpay.open();
        });
    </script>
</x-app-layout>
