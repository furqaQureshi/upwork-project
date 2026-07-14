@extends('layouts.app')

@section('title', 'Seller Profile Settings')

@section('content')
    @php
        $profileVerification = $user->applicableSellerVerificationForCategory();
        $profilePackage = $profileVerification?->subscriptionPackage;
        $profileBadgeLabel = trim((string) ($profilePackage?->resolved_seller_badge_label ?? ''));
        if ($profileBadgeLabel === '') {
            $profileBadgeLabel = 'Verified';
        }
        $hasScopedVerification = $profileVerification !== null;
    @endphp
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-8">
        <div class="container mx-auto px-4 max-w-4xl">
            <!-- Header -->
            <div class="mb-8">
                <a href="{{ route('seller.dashboard') }}" class="inline-flex items-center text-blue-600 hover:text-blue-700 mb-4">
                    <span>← Back to Dashboard</span>
                </a>
                <h1 class="text-4xl font-display font-bold text-slate-900 mb-2">Seller Profile</h1>
                <p class="text-slate-600">Manage your seller information and settings</p>
            </div>

            @if($errors->any())
                <div class="mb-6 rounded-3xl border border-rose-200 bg-rose-50 p-6">
                    <h3 class="font-semibold text-rose-900 mb-2">There were some errors:</h3>
                    <ul class="list-inside space-y-1 text-sm text-rose-700">
                        @foreach($errors->all() as $error)
                            <li>• {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('success'))
                <div class="mb-6 rounded-3xl border border-emerald-200 bg-emerald-50 p-6">
                    <p class="text-emerald-700">{{ session('success') }}</p>
                </div>
            @endif

            <form action="{{ route('seller.profile.update') }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Business Information -->
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                        <h2 class="font-display font-bold text-slate-900">Business Information</h2>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label for="business_name" class="block text-sm font-semibold text-slate-900 mb-2">Business Name</label>
                            <input type="text" id="business_name" name="business_name" value="{{ old('business_name', $user->business_name) }}" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="Your business name">
                        </div>

                        <div>
                            <label for="business_description" class="block text-sm font-semibold text-slate-900 mb-2">Business Description</label>
                            <textarea id="business_description" name="business_description" rows="4" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="Tell buyers about your business">{{ old('business_description', $user->business_description) }}</textarea>
                            <p class="mt-1 text-xs text-slate-600">Max 1000 characters</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                        <h2 class="font-display font-bold text-slate-900">Contact Information</h2>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="phone" class="block text-sm font-semibold text-slate-900 mb-2">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="+91 xxxxxxxxxx">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-semibold text-slate-900 mb-2">Email Address</label>
                                <input type="email" id="email" disabled value="{{ $user->email }}" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-2 text-slate-600 cursor-not-allowed">
                                <p class="mt-1 text-xs text-slate-600">Contact support to change email</p>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="city" class="block text-sm font-semibold text-slate-900 mb-2">City</label>
                                <input type="text" id="city" name="city" value="{{ old('city', $user->city) }}" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="Your city">
                            </div>

                            <div>
                                <label for="state" class="block text-sm font-semibold text-slate-900 mb-2">State</label>
                                <input type="text" id="state" name="state" value="{{ old('state', $user->state) }}" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="Your state">
                            </div>
                        </div>

                        <div>
                            <label for="address" class="block text-sm font-semibold text-slate-900 mb-2">Address</label>
                            <input type="text" id="address" name="address" value="{{ old('address', $user->address) }}" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="Full address">
                        </div>

                        <div>
                            <label for="pincode" class="block text-sm font-semibold text-slate-900 mb-2">Pincode</label>
                            <input type="text" id="pincode" name="pincode" value="{{ old('pincode', $user->pincode) }}" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="XXXXXX">
                        </div>
                    </div>
                </div>

                <!-- Business Hours -->
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                        <h2 class="font-display font-bold text-slate-900">Business Hours & Response</h2>
                    </div>
                    <div class="p-6 space-y-5">
                        <div>
                            <label for="response_time" class="block text-sm font-semibold text-slate-900 mb-2">Average Response Time (in hours)</label>
                            <input type="number" id="response_time" name="response_time" value="{{ old('response_time', $user->response_time ?? 24) }}" min="1" max="168" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="24">
                            <p class="mt-1 text-xs text-slate-600">Used to calculate response time badge on your listings</p>
                        </div>

                        <div>
                            <label for="business_hours" class="block text-sm font-semibold text-slate-900 mb-2">Business Hours (Optional)</label>
                            <textarea id="business_hours" name="business_hours" rows="3" class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="Mon-Fri: 9 AM - 6 PM&#10;Sat: 10 AM - 4 PM&#10;Sun: Closed">{{ old('business_hours', $user->business_hours) }}</textarea>
                            <p class="mt-1 text-xs text-slate-600">Format each day on a new line</p>
                        </div>
                    </div>
                </div>

                <!-- Account Status -->
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                        <h2 class="font-display font-bold text-slate-900">Account Status</h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 p-4">
                                <div>
                                    <p class="font-semibold text-slate-900">Verification Status</p>
                                    <p class="text-sm text-slate-600">{{ ucfirst($user->seller_verification_status ?? 'Not Verified') }}</p>
                                </div>
                                <span class="inline-flex rounded-lg bg-{{ $hasScopedVerification ? 'emerald' : 'slate' }}-100 px-3 py-1 text-xs font-semibold text-{{ $hasScopedVerification ? 'emerald' : 'slate' }}-700">
                                    {{ $hasScopedVerification ? '✓ Verified' : 'Not Verified' }}
                                </span>
                            </div>

                            @if($hasScopedVerification)
                                <div class="flex items-center justify-between rounded-lg bg-slate-50 p-4">
                                    <div>
                                        <p class="font-semibold text-slate-900">Seller Tier</p>
                                        <p class="text-sm text-slate-600">{{ $profilePackage?->seller_tier ?? $user->seller_type }}</p>
                                    </div>
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                                        <x-heroicon name="check-badge" class="h-4 w-4" />
                                        {{ $profileBadgeLabel }}
                                    </span>
                                </div>
                            @endif

                            <div class="flex items-center justify-between rounded-lg bg-slate-50 p-4">
                                <div>
                                    <p class="font-semibold text-slate-900">Member Since</p>
                                    <p class="text-sm text-slate-600">{{ $user->created_at->format('M d, Y') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-4 justify-end">
                    <a href="{{ route('seller.dashboard') }}" class="rounded-lg border border-slate-200 px-6 py-2 font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                    <button type="submit" class="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white hover:bg-blue-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
@endsection
