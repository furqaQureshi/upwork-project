@extends('layouts.app')

@section('title', 'Apply for Seller Verification')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-8">
    <div class="container mx-auto px-4 max-w-6xl">

        {{-- Back Link --}}
        <a href="{{ route('seller-verification.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 mb-6 transition">
            â† Back to My Verifications
        </a>

        <div class="grid gap-8 lg:grid-cols-3">
            {{-- Main Form --}}
            <div class="lg:col-span-2">
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="bg-emerald-600 px-7 py-5">
                        <h2 class="text-xl font-bold text-white">Become a Verified Seller</h2>
                        <p class="text-emerald-100 text-sm mt-0.5">Complete the form to submit your verification request</p>
                    </div>

                    <div class="p-7">
                        {{-- Existing Verification Alert --}}
                        @if($existingVerification)
                            @php
                                $alertBg = $existingVerification->isApproved() ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                                    : ($existingVerification->isRejected() ? 'bg-red-50 border-red-300 text-red-800'
                                    : 'bg-amber-50 border-amber-300 text-amber-800');
                            @endphp
                            <div class="rounded-xl border {{ $alertBg }} p-4 mb-6 flex items-start gap-3">
                                <div class="flex-1">
                                    <p class="text-sm font-semibold">Current Verification Status:
                                        <span class="uppercase">{{ $existingVerification->status }}</span>
                                    </p>
                                    <p class="text-xs mt-1">Last updated: {{ $existingVerification->updated_at->diffForHumans() }}</p>
                                    <a href="{{ route('seller-verification.show', $existingVerification) }}"
                                       class="mt-2 inline-block text-xs font-semibold underline">View Details â†’</a>
                                </div>
                            </div>
                        @endif

                        <form action="{{ route('seller-verification.store') }}" method="POST" enctype="multipart/form-data"
                              id="verificationForm" novalidate>
                            @csrf

                            {{-- Package Selection --}}
                            <div class="mb-8">
                                <h3 class="text-base font-bold text-slate-800 mb-4">ðŸ“¦ Select Verification Package</h3>

                                @if($packages->isEmpty())
                                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                                        No seller verification packages are available at this time.
                                    </div>
                                @else
                                    <div class="space-y-3">
                                        @foreach($packages as $package)
                                            <label for="package{{ $package->id }}"
                                                   class="relative flex gap-4 rounded-2xl border-2 border-slate-200 bg-white p-4 cursor-pointer hover:border-emerald-400 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                                                <input class="mt-1 accent-emerald-600" type="radio" name="subscription_package_id"
                                                       id="package{{ $package->id }}" value="{{ $package->id }}" required>
                                                <div class="flex-1">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <span class="text-base font-bold text-slate-800">{{ $package->name }}</span>
                                                        <span class="rounded-full bg-emerald-100 text-emerald-800 px-3 py-0.5 text-sm font-semibold">â‚¹{{ number_format($package->final_price, 2) }}</span>
                                                    </div>
                                                    <div class="flex flex-wrap gap-1.5 mb-2">
                                                        <span class="rounded-full bg-slate-100 text-slate-700 px-2.5 py-0.5 text-xs">{{ $package->seller_tier_label }}</span>
                                                        <span class="rounded-full bg-blue-100 text-blue-700 px-2.5 py-0.5 text-xs">{{ $package->category?->display_name ?? 'All Categories' }}</span>
                                                        <span class="rounded-full bg-purple-100 text-purple-700 px-2.5 py-0.5 text-xs">{{ $package->resolved_seller_badge_label }}</span>
                                                    </div>
                                                    <p class="text-xs text-slate-500 mb-2">
                                                        Valid: {{ $package->package_duration_type === 'unlimited' ? 'Unlimited' : $package->package_duration_days . ' day(s)' }}
                                                    </p>
                                                    @if($package->key_points && count($package->key_points) > 0)
                                                        <ul class="text-xs text-slate-600 space-y-1">
                                                            @foreach($package->key_points as $point)
                                                                <li class="flex items-start gap-1.5">
                                                                    <span class="text-emerald-500 mt-0.5">âœ“</span> {{ $point }}
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('subscription_package_id')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                @endif
                            </div>

                            <hr class="border-slate-100 my-6">

                            {{-- Document Upload --}}
                            <div class="mb-8">
                                <h3 class="text-base font-bold text-slate-800 mb-4">ðŸ“ Upload One Verification Document</h3>
                                <p class="mb-3 text-xs text-slate-500">Submit exactly one: Company/Firm Certificate or Aadhar or PAN.</p>
                                @error('documents')
                                    <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror

                                {{-- Company Certificate --}}
                                <div class="mb-5">
                                    <label for="company_certificate" class="block text-sm font-semibold text-slate-700 mb-1">
                                        Company / Firm Certificate
                                    </label>
                                    <p class="text-xs text-slate-400 mb-2">Accepted: PDF, JPG, PNG Â· Max 5 MB</p>
                                    <input class="w-full rounded-xl border {{ $errors->has('company_certificate') ? 'border-red-400' : 'border-slate-200' }} bg-white px-4 py-2.5 text-sm text-slate-800 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700"
                                           type="file" id="company_certificate" name="company_certificate"
                                           accept=".pdf,.jpg,.jpeg,.png">
                                    @error('company_certificate')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Aadhar --}}
                                <div class="mb-5 grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="aadhar" class="block text-sm font-semibold text-slate-700 mb-1">
                                            Aadhar Document
                                        </label>
                                        <p class="text-xs text-slate-400 mb-2">PDF, JPG, PNG Â· Max 5 MB</p>
                                        <input class="w-full rounded-xl border {{ $errors->has('aadhar') ? 'border-red-400' : 'border-slate-200' }} bg-white px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700"
                                               type="file" id="aadhar" name="aadhar" accept=".pdf,.jpg,.jpeg,.png">
                                        @error('aadhar')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="aadhar_number" class="block text-sm font-semibold text-slate-700 mb-1">
                                            Aadhar Number
                                        </label>
                                        <input class="w-full rounded-xl border {{ $errors->has('aadhar_number') ? 'border-red-400' : 'border-slate-200' }} bg-white px-4 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                               type="text" id="aadhar_number" name="aadhar_number"
                                               placeholder="XXXX XXXX XXXX"
                                               pattern="\d{12}" maxlength="12" inputmode="numeric"
                                               value="{{ old('aadhar_number') }}">
                                        <p class="text-xs text-slate-400 mt-1">12-digit number</p>
                                        @error('aadhar_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                {{-- PAN --}}
                                <div class="mb-5 grid sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="pan" class="block text-sm font-semibold text-slate-700 mb-1">
                                            PAN Document
                                        </label>
                                        <p class="text-xs text-slate-400 mb-2">PDF, JPG, PNG Â· Max 5 MB</p>
                                        <input class="w-full rounded-xl border {{ $errors->has('pan') ? 'border-red-400' : 'border-slate-200' }} bg-white px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-700"
                                               type="file" id="pan" name="pan" accept=".pdf,.jpg,.jpeg,.png">
                                        @error('pan')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="pan_number" class="block text-sm font-semibold text-slate-700 mb-1">
                                            PAN Number
                                        </label>
                                        <input class="w-full rounded-xl border {{ $errors->has('pan_number') ? 'border-red-400' : 'border-slate-200' }} bg-white px-4 py-2.5 text-sm text-slate-800 uppercase focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                               type="text" id="pan_number" name="pan_number"
                                               placeholder="ABCDE1234F"
                                               pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" maxlength="10"
                                               style="text-transform:uppercase"
                                               value="{{ old('pan_number') }}">
                                        <p class="text-xs text-slate-400 mt-1">Format: ABCDE1234F</p>
                                        @error('pan_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Notice --}}
                            <div class="rounded-xl bg-blue-50 border border-blue-200 p-4 mb-6">
                                <p class="text-sm font-semibold text-blue-800 mb-2">ðŸ’¡ Important Information</p>
                                <ul class="text-xs text-blue-700 space-y-1">
                                    <li>â€¢ All documents must be clear and legible</li>
                                    <li>â€¢ Admin will review within 2â€“3 business days</li>
                                    <li>â€¢ Ensure all information matches your account details</li>
                                    <li>â€¢ Rejection can be appealed by resubmitting correct documents</li>
                                </ul>
                            </div>

                            {{-- Actions --}}
                            <div class="flex flex-col sm:flex-row gap-3">
                                <a href="{{ route('seller-verification.index') }}"
                                   class="flex-1 text-center rounded-xl border border-slate-200 bg-slate-50 px-5 py-3 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                                    â† My Verifications
                                </a>
                                <button class="flex-1 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700 transition" type="submit">
                                    Submit for Verification âœ“
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Benefits --}}
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="bg-emerald-600 px-5 py-4">
                        <h3 class="text-base font-bold text-white">â­ Benefits of Verification</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        @foreach([
                            ['ðŸ†', 'Verified Seller Badge', 'Display on profile and listings'],
                            ['ðŸ‘¥', 'Increased Buyer Trust', 'More inquiries and better conversion'],
                            ['ðŸ‘', 'Higher Visibility', 'Featured in verified dealers section'],
                            ['ðŸŽ§', 'Priority Support', 'Dedicated customer support team'],
                            ['ðŸ“Š', 'Advanced Analytics', 'Track performance and insights'],
                        ] as [$icon, $title, $desc])
                            <div class="flex items-start gap-3">
                                <span class="text-xl">{{ $icon }}</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ $title }}</p>
                                    <p class="text-xs text-slate-500">{{ $desc }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Help --}}
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="bg-blue-600 px-5 py-4">
                        <h3 class="text-base font-bold text-white">â“ Need Help?</h3>
                    </div>
                    <div class="p-5">
                        <p class="text-sm text-slate-500 mb-4">
                            Have questions about the verification process? Contact our support team.
                        </p>
                        <div class="space-y-2">
                            <a href="/help/seller-verification"
                               class="block w-full text-center rounded-xl border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100 transition">
                                View Help Documentation
                            </a>
                            <a href="mailto:support@example.com"
                               class="block w-full text-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                                Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
document.getElementById('aadhar_number').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/\D/g, '').slice(0, 12);
});
document.getElementById('pan_number').addEventListener('input', function(e) {
    e.target.value = e.target.value.toUpperCase();
});
</script>
@endsection
