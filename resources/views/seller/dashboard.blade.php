@extends('layouts.app')

@section('title', 'Seller Dashboard')

@section('content')
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-8">
        <div class="container mx-auto px-4 max-w-7xl">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-4xl font-display font-bold text-slate-900 mb-2">Seller Dashboard</h1>
                <p class="text-slate-600">Welcome back, {{ auth()->user()->name }}!</p>
            </div>

            <!-- Status Overview -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-8">
                <!-- Verification Status -->
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Verification Status</p>
                            <p class="text-2xl font-bold text-slate-900 mt-2">{{ ucfirst($stats['verification_status']) }}</p>
                        </div>
                        <div class="text-3xl">
                            @if($isVerified)
                                <span class="text-emerald-600">✓</span>
                            @else
                                <span class="text-amber-600">⏳</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Seller Tier -->
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
                    <div>
                        <p class="text-xs text-slate-600 uppercase tracking-wide">Seller Tier</p>
                        <p class="text-2xl font-bold text-slate-900 mt-2">{{ $stats['seller_tier'] }}</p>
                        @if($isVerified)
                            <p class="text-xs text-emerald-600 mt-1">✓ Verified</p>
                        @else
                            <a href="{{ route('seller.verification') }}" class="text-xs text-blue-600 hover:underline mt-1 block">Get Verified →</a>
                        @endif
                    </div>
                </div>

                <!-- Active Listings -->
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
                    <div>
                        <p class="text-xs text-slate-600 uppercase tracking-wide">Active Listings</p>
                        <p class="text-2xl font-bold text-slate-900 mt-2">{{ $stats['total_listings'] }}</p>
                        <a href="{{ route('listings.index') }}" class="text-xs text-blue-600 hover:underline mt-1 block">View All →</a>
                    </div>
                </div>

                <!-- Unread Messages -->
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm hover:shadow-md transition">
                    <div>
                        <p class="text-xs text-slate-600 uppercase tracking-wide">Unread Messages</p>
                        <p class="text-2xl font-bold text-slate-900 mt-2">{{ $stats['unread_messages'] }}</p>
                        <a href="{{ route('messages.index') }}" class="text-xs text-blue-600 hover:underline mt-1 block">View Inbox →</a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Listings Overview -->
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                            <h2 class="font-display font-bold text-slate-900">Listing Overview</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid gap-4 md:grid-cols-4">
                                <div class="rounded-xl bg-blue-50 p-4 text-center">
                                    <p class="text-3xl font-bold text-blue-600">{{ $stats['total_listings'] }}</p>
                                    <p class="text-sm text-blue-700 mt-1">Active</p>
                                </div>
                                <div class="rounded-xl bg-emerald-50 p-4 text-center">
                                    <p class="text-3xl font-bold text-emerald-600">{{ $stats['sold_listings'] }}</p>
                                    <p class="text-sm text-emerald-700 mt-1">Sold</p>
                                </div>
                                <div class="rounded-xl bg-amber-50 p-4 text-center">
                                    <p class="text-3xl font-bold text-amber-600">{{ $stats['draft_listings'] }}</p>
                                    <p class="text-sm text-amber-700 mt-1">Draft</p>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-4 text-center">
                                    <p class="text-3xl font-bold text-slate-600">{{ $stats['expired_listings'] }}</p>
                                    <p class="text-sm text-slate-700 mt-1">Expired</p>
                                </div>
                            </div>
                            <div class="mt-4 flex gap-3">
                                <a href="{{ route('listings.create') }}" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700">Create Listing</a>
                                <a href="{{ route('listings.index') }}" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-50">Manage Listings</a>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Listings -->
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4 flex items-center justify-between">
                            <h2 class="font-display font-bold text-slate-900">Recent Listings</h2>
                            <a href="{{ route('listings.index') }}" class="text-sm text-blue-600 hover:text-blue-700">View All →</a>
                        </div>
                        <div class="p-6">
                            @if($recentListings->count() > 0)
                                <div class="space-y-3">
                                    @foreach($recentListings as $listing)
                                        <div class="flex items-center justify-between rounded-lg border border-slate-200 p-3 hover:bg-slate-50">
                                            <div class="flex-1">
                                                <h4 class="font-semibold text-slate-900">{{ $listing->title }}</h4>
                                                <p class="text-sm text-slate-600">
                                                    @if($listing->category)
                                                        {{ $listing->category->name }}
                                                    @endif
                                                    • {{ $listing->published_at?->format('M d, Y') }}
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p class="font-semibold text-slate-900">₹{{ number_format($listing->price ?? 0) }}</p>
                                                <span class="inline-flex rounded-lg bg-{{ $listing->status === 'active' ? 'emerald' : 'slate' }}-100 px-2 py-1 text-xs font-semibold text-{{ $listing->status === 'active' ? 'emerald' : 'slate' }}-700">{{ ucfirst($listing->status) }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-center text-slate-600 py-8">No listings yet. <a href="{{ route('listings.create') }}" class="text-blue-600 hover:underline">Create your first listing</a></p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar -->
                <div class="space-y-6">
                    <!-- Verification Card -->
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                            <h3 class="font-display font-bold text-slate-900">Verification</h3>
                        </div>
                        <div class="p-6">
                            @if($isVerified)
                                <div class="text-center mb-4">
                                    <div class="text-4xl text-emerald-600 mb-2">✓</div>
                                    <p class="font-bold text-slate-900">You're Verified!</p>
                                    <p class="text-sm text-slate-600 mt-1">{{ $verification?->category?->name ?? 'Seller Verified' }}</p>
                                </div>
                                <a href="{{ route('seller.verification') }}" class="block w-full rounded-lg border border-slate-200 px-4 py-2 text-center text-sm font-semibold text-slate-600 hover:bg-slate-50">View Details</a>
                            @elseif($pendingVerification)
                                <div class="mb-4">
                                    <div class="text-3xl text-amber-600 mb-2">⏳</div>
                                    <p class="font-bold text-slate-900">Verification Pending</p>
                                    <p class="text-sm text-slate-600 mt-1">Submitted {{ $pendingVerification->created_at->format('M d, Y') }}</p>
                                </div>
                                <a href="{{ route('seller-verification.show', $pendingVerification) }}" class="block w-full rounded-lg bg-amber-50 px-4 py-2 text-center text-sm font-semibold text-amber-700 hover:bg-amber-100">Check Status</a>
                            @else
                                <div class="mb-4">
                                    <p class="font-bold text-slate-900">Get Verified</p>
                                    <p class="text-sm text-slate-600 mt-1">Build trust with buyers by verifying your seller account</p>
                                </div>
                                <a href="{{ route('seller.verification') }}" class="block w-full rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700">Start Verification</a>
                            @endif
                        </div>
                    </div>

                    <!-- Active Package -->
                    @if($activePackage)
                        <div class="rounded-3xl border border-blue-200 bg-blue-50 shadow-sm overflow-hidden">
                            <div class="border-b border-blue-200 bg-blue-100 px-6 py-4">
                                <h3 class="font-display font-bold text-blue-900">Active Package</h3>
                            </div>
                            <div class="p-6">
                                <p class="font-bold text-blue-900">{{ $activePackage->name }}</p>
                                <p class="text-sm text-blue-700 mt-1">{{ $activePackage->description }}</p>
                                @if($packageExpiry)
                                    <p class="text-xs text-blue-600 mt-3">Expires: {{ $packageExpiry->format('M d, Y') }}</p>
                                @endif
                                <a href="{{ route('subscriptions.plans') }}" class="mt-4 block rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700">Upgrade Package</a>
                            </div>
                        </div>
                    @else
                        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm p-6">
                            <p class="font-bold text-slate-900">No Active Package</p>
                            <p class="text-sm text-slate-600 mt-1">Purchase a seller package to unlock premium features</p>
                            <a href="{{ route('subscriptions.plans') }}" class="mt-4 block rounded-lg bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-blue-700">Browse Packages</a>
                        </div>
                    @endif

                    <!-- Quick Actions -->
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm p-6">
                        <h3 class="font-display font-bold text-slate-900 mb-4">Quick Actions</h3>
                        <div class="space-y-2">
                            <a href="{{ route('seller.analytics') }}" class="flex items-center gap-3 rounded-lg p-3 hover:bg-slate-50 border border-transparent hover:border-slate-200">
                                <span class="text-lg">📊</span>
                                <span class="text-sm font-semibold text-slate-700">View Analytics</span>
                            </a>
                            <a href="{{ route('seller.profile') }}" class="flex items-center gap-3 rounded-lg p-3 hover:bg-slate-50 border border-transparent hover:border-slate-200">
                                <span class="text-lg">⚙️</span>
                                <span class="text-sm font-semibold text-slate-700">Settings</span>
                            </a>
                            <a href="{{ route('messages.index') }}" class="flex items-center gap-3 rounded-lg p-3 hover:bg-slate-50 border border-transparent hover:border-slate-200">
                                <span class="text-lg">💬</span>
                                <span class="text-sm font-semibold text-slate-700">Messages</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
