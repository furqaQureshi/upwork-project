@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-3xl bg-gradient-to-br from-orange-500 to-orange-600 p-5 text-white shadow-xl">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-orange-100">Total Users</p>
            <p class="mt-2 font-display text-4xl font-bold">{{ $stats['users'] }}</p>
        </div>
        <div class="rounded-3xl bg-gradient-to-br from-teal-500 to-cyan-600 p-5 text-white shadow-xl">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-teal-100">Pending Listings</p>
            <p class="mt-2 font-display text-4xl font-bold">{{ $stats['pending_listings'] }}</p>
        </div>
        <div class="rounded-3xl bg-gradient-to-br from-rose-500 to-pink-600 p-5 text-white shadow-xl">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-rose-100">Open Reports</p>
            <p class="mt-2 font-display text-4xl font-bold">{{ $stats['open_reports'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Approved</p>
            <p class="mt-2 font-display text-4xl font-bold text-emerald-600">{{ $stats['approved_listings'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Sold Listings</p>
            <p class="mt-2 font-display text-4xl font-bold text-slate-900">{{ $stats['sold_listings'] }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-white p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Blocked Users</p>
            <p class="mt-2 font-display text-4xl font-bold text-rose-600">{{ $stats['blocked_users'] }}</p>
        </div>
    </section>

    <section class="grid gap-5 lg:grid-cols-2">
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-display text-xl font-bold text-slate-900">Recent Listings</h2>
                <a href="{{ route('admin.listings.index') }}" class="text-sm font-semibold text-orange-600">Manage</a>
            </div>
            <div class="space-y-3">
                @forelse ($recentListings as $listing)
                    <a href="{{ route('admin.listings.show', $listing) }}" class="flex items-center justify-between rounded-2xl bg-slate-50 px-3 py-2 transition hover:bg-slate-100">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $listing->title }}</p>
                            <p class="text-xs text-slate-500">{{ $listing->user->name }} • {{ ucfirst($listing->status) }}</p>
                        </div>
                        <span class="text-sm font-bold text-slate-700">₹{{ number_format((float) $listing->price) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-slate-600">No listings found.</p>
                @endforelse
            </div>
        </article>

        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-display text-xl font-bold text-slate-900">Open Reports</h2>
                <a href="{{ route('admin.listings.index', ['status' => 'pending']) }}" class="text-sm font-semibold text-orange-600">Review</a>
            </div>
            <div class="space-y-3">
                @forelse ($latestReports as $report)
                    <div class="rounded-2xl bg-slate-50 px-3 py-2">
                        <p class="font-semibold text-slate-900">{{ $report->listing->title }}</p>
                        <p class="text-xs text-slate-500">Reason: {{ $report->reason }} • by {{ $report->user->name }}</p>
                        <div class="mt-2 flex gap-2">
                            <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
                                @csrf
                                <button type="submit" class="rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">Resolve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.reports.dismiss', $report) }}">
                                @csrf
                                <button type="submit" class="rounded-xl bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white">Dismiss</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-600">No open reports right now.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="font-display text-xl font-bold text-slate-900">Newest Users</h2>
            <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-orange-600">Manage users</a>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($recentUsers as $user)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                    <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                    <p class="text-xs text-slate-500">{{ $user->email }}</p>
                    <p class="mt-2 text-xs font-semibold uppercase tracking-wide {{ $user->is_admin ? 'text-orange-600' : 'text-slate-500' }}">
                        {{ $user->is_admin ? 'Admin' : 'User' }}
                    </p>
                </div>
            @endforeach
        </div>
    </section>
@endsection
