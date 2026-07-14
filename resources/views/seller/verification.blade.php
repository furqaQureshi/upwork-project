@extends('layouts.app')

@section('title', 'Seller Verification')

@section('content')
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-8">
        <div class="container mx-auto px-4 max-w-6xl">
            <!-- Header -->
            <div class="mb-8">
                <a href="{{ route('seller.dashboard') }}" class="inline-flex items-center text-blue-600 hover:text-blue-700 mb-4">
                    <span>← Back to Dashboard</span>
                </a>
                <h1 class="text-4xl font-display font-bold text-slate-900 mb-2">Seller Verification</h1>
                <p class="text-slate-600">Manage your seller verification and get access to premium selling features</p>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    @if($isVerified)
                        <!-- Verified Status -->
                        <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-8 shadow-sm text-center">
                            <div class="text-6xl mb-4">✓</div>
                            <h2 class="text-2xl font-bold text-emerald-900 mb-2">You're a Verified Seller!</h2>
                            <p class="text-emerald-700 mb-6">Your account is verified and you have access to all premium seller features</p>
                            <div class="inline-flex gap-3">
                                <a href="{{ route('listings.create') }}" class="rounded-lg bg-emerald-600 px-6 py-2 font-semibold text-white hover:bg-emerald-700">Create Listing</a>
                                <a href="{{ route('seller.analytics') }}" class="rounded-lg border border-emerald-300 px-6 py-2 font-semibold text-emerald-700 hover:bg-emerald-100">View Analytics</a>
                            </div>
                        </div>
                    @elseif($verifications->where('status', 'pending')->count() > 0)
                        <!-- Pending Status -->
                        <div class="rounded-3xl border border-amber-200 bg-amber-50 p-8 shadow-sm text-center">
                            <div class="text-6xl mb-4">⏳</div>
                            <h2 class="text-2xl font-bold text-amber-900 mb-2">Verification Pending</h2>
                            <p class="text-amber-700 mb-4">Your verification is being reviewed by our team</p>
                            <p class="text-sm text-amber-600 mb-6">This usually takes 24-48 hours</p>
                        </div>
                    @endif

                    <!-- Verification History -->
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                            <h2 class="font-display font-bold text-slate-900">Your Verifications</h2>
                        </div>
                        <div>
                            @if($verifications->count() > 0)
                                <div class="divide-y divide-slate-200">
                                    @foreach($verifications as $verification)
                                        <div class="p-6 hover:bg-slate-50 transition">
                                            <div class="flex items-center justify-between mb-3">
                                                <div class="flex-1">
                                                    <h3 class="text-lg font-semibold text-slate-900">
                                                        {{ $verification->category?->name ?? 'Seller Verification' }}
                                                    </h3>
                                                    <p class="text-sm text-slate-600">
                                                        Package: {{ $verification->subscriptionPackage?->name }}
                                                    </p>
                                                </div>
                                                <span class="inline-flex rounded-lg bg-{{ $verification->status === 'approved' ? 'emerald' : ($verification->status === 'rejected' ? 'rose' : 'amber') }}-100 px-3 py-1 text-sm font-semibold text-{{ $verification->status === 'approved' ? 'emerald' : ($verification->status === 'rejected' ? 'rose' : 'amber') }}-700">
                                                    @if($verification->status === 'approved')
                                                        ✓ Approved
                                                    @elseif($verification->status === 'rejected')
                                                        ✗ Rejected
                                                    @else
                                                        ⏳ Pending
                                                    @endif
                                                </span>
                                            </div>

                                            <div class="mb-4 grid gap-3 md:grid-cols-3 text-sm">
                                                <div>
                                                    <p class="text-slate-600">Submitted</p>
                                                    <p class="font-semibold text-slate-900">{{ $verification->created_at->format('M d, Y') }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-slate-600">Documents</p>
                                                    <p class="font-semibold text-slate-900">{{ $verification->documents->count() }} uploaded</p>
                                                </div>
                                                <div>
                                                    <p class="text-slate-600">Verified</p>
                                                    <p class="font-semibold text-slate-900">
                                                        @if($verification->verified_at)
                                                            {{ $verification->verified_at->format('M d, Y') }}
                                                        @else
                                                            Pending
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>

                                            <div class="flex gap-2">
                                                <a href="{{ route('seller-verification.show', $verification) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">View Details</a>
                                                @if($verification->status === 'rejected')
                                                    <a href="{{ route('seller-verification.create', ['resubmit' => $verification->id]) }}" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Resubmit</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-12 text-center">
                                    <p class="text-slate-600 mb-4">No verifications yet</p>
                                    <a href="{{ route('seller-verification.create') }}" class="inline-flex rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white hover:bg-blue-700">Start Verification</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Available Packages -->
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                            <h2 class="font-display font-bold text-slate-900">Available Packages</h2>
                        </div>
                        <div class="p-6">
                            @if($availablePackages->count() > 0)
                                <div class="space-y-3">
                                    @foreach($availablePackages as $package)
                                        <div class="rounded-lg border border-slate-200 p-4 hover:shadow-md transition">
                                            <h3 class="font-semibold text-slate-900">{{ $package->name }}</h3>
                                            <p class="text-sm text-slate-600 mt-1">{{ Str::limit($package->description, 50) }}</p>
                                            <div class="mt-3 flex items-center justify-between">
                                                <p class="text-lg font-bold text-slate-900">₹{{ number_format($package->final_price ?? $package->price) }}</p>
                                                <a href="{{ route('seller-verification.create', ['package' => $package->id]) }}" class="text-sm text-blue-600 hover:text-blue-700 font-semibold">Choose →</a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-center text-slate-600 py-4">No packages available</p>
                            @endif
                        </div>
                    </div>

                    <!-- Benefits -->
                    <div class="rounded-3xl border border-blue-200 bg-blue-50 p-6 shadow-sm">
                        <h3 class="font-display font-bold text-blue-900 mb-4">Verification Benefits</h3>
                        <ul class="space-y-2 text-sm text-blue-700">
                            <li class="flex items-center gap-2">
                                <span class="text-lg">✓</span>
                                <span>Build buyer trust</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-lg">✓</span>
                                <span>Higher visibility</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-lg">✓</span>
                                <span>Premium badge</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-lg">✓</span>
                                <span>Featured listings</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <span class="text-lg">✓</span>
                                <span>Priority support</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Help -->
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="font-display font-bold text-slate-900 mb-3">Need Help?</h3>
                        <p class="text-sm text-slate-600 mb-4">Learn more about the seller verification process and requirements</p>
                        <a href="/docs/seller-verification" class="inline-flex text-blue-600 hover:text-blue-700 text-sm font-semibold">
                            View Guide →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
