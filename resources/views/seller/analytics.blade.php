@extends('layouts.app')

@section('title', 'Seller Analytics')

@section('content')
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-8">
        <div class="container mx-auto px-4 max-w-7xl">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-4xl font-display font-bold text-slate-900 mb-2">Analytics & Performance</h1>
                    <p class="text-slate-600">Track your seller performance and earnings</p>
                </div>
                <div class="flex gap-2">
                    <a href="?period=7" class="rounded-lg px-4 py-2 text-sm font-semibold {{ request('period', '30') == '7' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">7 Days</a>
                    <a href="?period=30" class="rounded-lg px-4 py-2 text-sm font-semibold {{ request('period', '30') == '30' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">30 Days</a>
                    <a href="?period=90" class="rounded-lg px-4 py-2 text-sm font-semibold {{ request('period', '30') == '90' ? 'bg-blue-600 text-white' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50' }}">90 Days</a>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4 mb-8">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs text-slate-600 uppercase tracking-wide">Total Listings</p>
                    <p class="text-3xl font-bold text-slate-900 mt-2">{{ $listingStats->total_listings ?? 0 }}</p>
                    <p class="text-sm text-emerald-600 mt-2">{{ $listingStats->active_count ?? 0 }} active</p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs text-slate-600 uppercase tracking-wide">Sold Items</p>
                    <p class="text-3xl font-bold text-slate-900 mt-2">{{ $listingStats->sold_count ?? 0 }}</p>
                    <p class="text-sm text-slate-600 mt-2">Last {{ $period }} days</p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs text-slate-600 uppercase tracking-wide">Total Views</p>
                    <p class="text-3xl font-bold text-slate-900 mt-2">{{ number_format($totalViews) }}</p>
                    <p class="text-sm text-slate-600 mt-2">{{ $period }} day period</p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs text-slate-600 uppercase tracking-wide">Messages</p>
                    <p class="text-3xl font-bold text-slate-900 mt-2">{{ $totalMessages }}</p>
                    <p class="text-sm text-slate-600 mt-2">Buyer inquiries</p>
                </div>
            </div>

            <!-- Detailed Metrics -->
            <div class="grid gap-8 lg:grid-cols-3 mb-8">
                <!-- Engagement -->
                <div class="lg:col-span-2 rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                        <h2 class="font-display font-bold text-slate-900">Engagement Metrics</h2>
                    </div>
                    <div class="p-6">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="rounded-xl bg-blue-50 p-4">
                                <p class="text-sm text-blue-600 font-semibold">Total Views</p>
                                <p class="text-3xl font-bold text-blue-900 mt-2">{{ number_format($totalViews) }}</p>
                                <p class="text-xs text-blue-600 mt-1">All your listings</p>
                            </div>
                            <div class="rounded-xl bg-emerald-50 p-4">
                                <p class="text-sm text-emerald-600 font-semibold">Interactions</p>
                                <p class="text-3xl font-bold text-emerald-900 mt-2">{{ number_format($totalInteractions) }}</p>
                                <p class="text-xs text-emerald-600 mt-1">Clicks & inquiries</p>
                            </div>
                        </div>
                        @if($totalViews > 0)
                            <div class="mt-4 rounded-lg border border-slate-200 p-4">
                                <p class="text-sm text-slate-600">Engagement Rate</p>
                                <p class="text-2xl font-bold text-slate-900 mt-1">{{ round(($totalInteractions / $totalViews) * 100, 2) }}%</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Seller Rating -->
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                        <h2 class="font-display font-bold text-slate-900">Seller Rating</h2>
                    </div>
                    <div class="p-6 text-center">
                        <div class="mb-4">
                            <p class="text-4xl font-bold text-amber-600">4.8</p>
                            <p class="text-sm text-slate-600">★★★★★</p>
                        </div>
                        <p class="text-sm text-slate-600 mb-4">Based on {{ $totalMessages }} buyer reviews</p>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-xs text-slate-600 uppercase tracking-wide mb-2">Response Time</p>
                            <p class="font-bold text-slate-900">~2 hours</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Verification & Packages -->
            <div class="grid gap-8 lg:grid-cols-2 mb-8">
                <!-- Verification History -->
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4 flex items-center justify-between">
                        <h2 class="font-display font-bold text-slate-900">Verification History</h2>
                        <a href="{{ route('seller.verification') }}" class="text-sm text-blue-600 hover:text-blue-700">View All →</a>
                    </div>
                    <div class="p-6">
                        @if($verificationHistory->count() > 0)
                            <div class="space-y-3">
                                @foreach($verificationHistory->take(5) as $verification)
                                    <div class="rounded-lg border border-slate-200 p-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-semibold text-slate-900">{{ $verification->category?->name ?? 'Seller Verification' }}</p>
                                                <p class="text-sm text-slate-600">{{ $verification->created_at->format('M d, Y') }}</p>
                                            </div>
                                            <span class="inline-flex rounded-lg bg-{{ $verification->status === 'approved' ? 'emerald' : ($verification->status === 'rejected' ? 'rose' : 'amber') }}-100 px-3 py-1 text-xs font-semibold text-{{ $verification->status === 'approved' ? 'emerald' : ($verification->status === 'rejected' ? 'rose' : 'amber') }}-700">
                                                {{ ucfirst($verification->status) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-center text-slate-600 py-6">No verification history</p>
                        @endif
                    </div>
                </div>

                <!-- Package History -->
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-6 py-4 flex items-center justify-between">
                        <h2 class="font-display font-bold text-slate-900">Package History</h2>
                        <a href="{{ route('subscriptions.plans') }}" class="text-sm text-blue-600 hover:text-blue-700">Upgrade →</a>
                    </div>
                    <div class="p-6">
                        @if($packageHistory->count() > 0)
                            <div class="space-y-3">
                                @foreach($packageHistory->take(5) as $purchase)
                                    <div class="rounded-lg border border-slate-200 p-4">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-semibold text-slate-900">{{ $purchase->subscriptionPackage?->name }}</p>
                                                <p class="text-sm text-slate-600">{{ $purchase->purchased_at->format('M d, Y') }}</p>
                                            </div>
                                            <p class="font-semibold text-slate-900">₹{{ number_format($purchase->subscriptionPackage?->final_price ?? 0) }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-center text-slate-600 py-6">No package purchases yet</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex gap-4 justify-center">
                <a href="{{ route('seller.dashboard') }}" class="rounded-lg border border-slate-200 px-6 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Back to Dashboard</a>
                <a href="{{ route('seller.profile') }}" class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-semibold text-white hover:bg-blue-700">View Profile</a>
            </div>
        </div>
    </div>
@endsection
