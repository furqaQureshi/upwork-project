@extends('layouts.app')

@section('title', 'My Seller Verifications')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-8">
    <div class="container mx-auto px-4 max-w-6xl">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 mb-1">My Verifications</h1>
                <p class="text-slate-500">Track your seller verification status across all categories</p>
            </div>
            <a href="{{ route('seller-verification.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition">
                + New Verification
            </a>
        </div>

        @if($verifications->isEmpty())
            {{-- Empty State --}}
            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm text-center py-20 px-6">
                <div class="text-6xl mb-4">📭</div>
                <h2 class="text-xl font-bold text-slate-800 mb-2">No Verifications Yet</h2>
                <p class="text-slate-500 mb-6 max-w-sm mx-auto">
                    You haven't submitted any seller verification requests. Start the verification process now.
                </p>
                <a href="{{ route('seller-verification.create') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-700 transition">
                    + Apply for Verification
                </a>
            </div>
        @else
            {{-- Filters --}}
            <form method="GET" class="mb-6 flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" name="search"
                           class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                           placeholder="Search verifications..."
                           value="{{ request('search') }}">
                </div>
                <div>
                    <select name="status"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="">All Status</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <button type="submit"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                    Filter
                </button>
            </form>

            {{-- Verifications Grid --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($verifications as $verification)
                    @php
                        $isApproved = $verification->isApproved();
                        $isRejected = $verification->isRejected();
                        $borderColor = $isApproved ? 'border-emerald-400' : ($isRejected ? 'border-red-400' : 'border-amber-400');
                        $headerBg   = $isApproved ? 'bg-emerald-600' : ($isRejected ? 'bg-red-600' : 'bg-amber-500');
                        $badgeBg    = $isApproved ? 'bg-emerald-100 text-emerald-800' : ($isRejected ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800');
                        $badgeText  = $isApproved ? 'Approved' : ($isRejected ? 'Rejected' : 'Pending');
                        $progress   = $isApproved ? 100 : ($isRejected ? 20 : 55);
                        $total      = $verification->documents->count();
                        $verified   = $verification->documents->where('verification_status', 'verified')->count();
                        $rejected   = $verification->documents->where('verification_status', 'rejected')->count();
                    @endphp
                    <div class="rounded-3xl border-2 {{ $borderColor }} bg-white shadow-sm flex flex-col overflow-hidden">
                        <div class="{{ $headerBg }} px-5 py-3 flex items-center justify-between">
                            <span class="text-sm font-bold text-white truncate">🏷 {{ $verification->category->name }}</span>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold bg-white/90 {{ $badgeBg }}">{{ $badgeText }}</span>
                        </div>

                        <div class="flex-1 p-5 space-y-4">
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wide">Package</p>
                                <p class="text-sm font-semibold text-slate-800">{{ $verification->subscriptionPackage?->name ?? '—' }}</p>
                            </div>

                            <div>
                                <div class="w-full h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full {{ $isApproved ? 'bg-emerald-500' : ($isRejected ? 'bg-red-400' : 'bg-amber-400') }}"
                                         style="width: {{ $progress }}%"></div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-1">
                                <span class="rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 text-xs font-medium">{{ $verified }} verified</span>
                                @if($total - $verified - $rejected > 0)
                                    <span class="rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-medium">{{ $total - $verified - $rejected }} pending</span>
                                @endif
                                @if($rejected > 0)
                                    <span class="rounded-full bg-red-100 text-red-800 px-2 py-0.5 text-xs font-medium">{{ $rejected }} rejected</span>
                                @endif
                            </div>

                            <p class="text-xs text-slate-400">
                                Submitted {{ $verification->created_at->format('M d, Y') }}
                                @if($verification->verified_at) · Verified {{ $verification->verified_at->format('M d, Y') }} @endif
                            </p>
                        </div>

                        <div class="border-t border-slate-100 px-5 py-3">
                            <a href="{{ route('seller-verification.show', $verification) }}"
                               class="block w-full text-center rounded-xl border border-slate-200 bg-slate-50 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                                View Details →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

                <!-- Pagination -->
                @if($verifications->hasPages())
                    <div class="mt-4">
                        {{ $verifications->links() }}
                    </div>
                @endif
            @endif
            </div>

            @if($verifications->hasPages())
                <div class="mt-8">{{ $verifications->links() }}</div>
            @endif
        @endif

    </div>
</div>
@endsection
