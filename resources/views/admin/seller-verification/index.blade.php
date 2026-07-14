@extends('admin.layout')

@section('title', 'Seller Verification')

@section('content')
    <section class="space-y-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-display text-2xl font-bold text-slate-900">Seller Verification</h2>
                <p class="text-sm text-slate-600">Review and manage seller verification requests</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.seller-verification.statistics') }}" class="app-btn-secondary">Statistics</a>
                <a href="{{ route('admin.seller-verification.export') }}" class="app-btn-secondary">Export</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</p>
                <p class="mt-1 text-3xl font-bold text-amber-600">{{ \App\Models\SellerVerification::where('status', 'pending')->count() }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Approved</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ \App\Models\SellerVerification::where('status', 'approved')->count() }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rejected</p>
                <p class="mt-1 text-3xl font-bold text-rose-600">{{ \App\Models\SellerVerification::where('status', 'rejected')->count() }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</p>
                <p class="mt-1 text-3xl font-bold text-blue-600">{{ \App\Models\SellerVerification::count() }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="GET" class="grid gap-3 md:grid-cols-5">
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="app-input" placeholder="Name or email">
                </div>
                <div>
                    <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>
                    <select name="status" class="app-select">
                        <option value="">All Status</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="flex items-end gap-2 md:col-span-3">
                    <button type="submit" class="app-btn-primary">Apply</button>
                    <a href="{{ route('admin.seller-verification.index') }}" class="app-btn-muted">Reset</a>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <th class="px-4 py-3">Seller</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Seller Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Documents</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($verifications as $verification)
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-slate-900">{{ $verification->user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $verification->user->email }}</p>
                            </td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-lg bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700">{{ $verification->category?->name ?? 'All' }}</span></td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ $verification->subscriptionPackage?->seller_tier_label ?? 'Package' }}</span></td>
                            <td class="px-4 py-3">
                                @if($verification->isApproved())
                                    <span class="inline-flex rounded-lg bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Approved</span>
                                @elseif($verification->isRejected())
                                    <span class="inline-flex rounded-lg bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-700">Rejected</span>
                                @else
                                    <span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">Pending</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @php $verified = $verification->documents->where('verification_status', 'verified')->count(); $total = $verification->documents->count(); @endphp
                                <span class="font-semibold text-slate-900">{{ $verified }}/{{ $total }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $verification->created_at->format('M d, Y') }}</td>
                            <td class="px-4 py-3"><a href="{{ route('admin.seller-verification.show', $verification) }}" class="app-btn-primary text-xs">Review</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No verifications found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($verifications->hasPages())
            <div class="flex justify-center">{{ $verifications->links() }}</div>
        @endif
    </section>
@endsection
