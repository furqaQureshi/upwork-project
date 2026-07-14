@extends('admin.layout')

@section('title', 'Seller Management')

@section('content')
    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('admin.sellers.index') }}" class="grid gap-3 md:grid-cols-6">
            <div class="md:col-span-2">
                <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
                <input id="q" type="text" name="q" value="{{ request('q') }}" class="app-input" placeholder="Name, email, phone, city">
            </div>
            <div>
                <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>
                <select id="status" name="status" class="app-select">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="blocked" @selected(request('status') === 'blocked')>Blocked</option>
                </select>
            </div>
            <div>
                <label for="verification" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Verification</label>
                <select id="verification" name="verification" class="app-select">
                    <option value="">All</option>
                    <option value="verified" @selected(request('verification') === 'verified')>Approved</option>
                    <option value="pending" @selected(request('verification') === 'pending')>Pending</option>
                    <option value="rejected" @selected(request('verification') === 'rejected')>Rejected</option>
                    <option value="unsubmitted" @selected(request('verification') === 'unsubmitted')>Unsubmitted</option>
                </select>
            </div>
            <div>
                <label for="listing_status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Listing Status</label>
                <select id="listing_status" name="listing_status" class="app-select">
                    <option value="">Any</option>
                    @foreach (['pending', 'approved', 'rejected', 'sold'] as $status)
                        <option value="{{ $status }}" @selected(request('listing_status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="sort" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Sort</label>
                <select id="sort" name="sort" class="app-select">
                    <option value="latest" @selected(request('sort', 'latest') === 'latest')>Latest</option>
                    <option value="most-listings" @selected(request('sort') === 'most-listings')>Most Listings</option>
                    <option value="active-recent" @selected(request('sort') === 'active-recent')>Last Seen</option>
                    <option value="oldest" @selected(request('sort') === 'oldest')>Oldest</option>
                </select>
            </div>
            <div class="md:col-span-6 flex flex-wrap items-end gap-2">
                <button type="submit" class="app-btn-primary">Apply Filters</button>
                <a href="{{ route('admin.sellers.index') }}" class="app-btn-muted">Reset</a>
            </div>
        </form>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Sellers</p>
            <p class="mt-1 font-display text-3xl font-bold text-slate-900">{{ $sellerStats['total'] }}</p>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Active</p>
            <p class="mt-1 font-display text-3xl font-bold text-emerald-600">{{ $sellerStats['active'] }}</p>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Blocked</p>
            <p class="mt-1 font-display text-3xl font-bold text-rose-600">{{ $sellerStats['blocked'] }}</p>
        </article>
        <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Verified</p>
            <p class="mt-1 font-display text-3xl font-bold text-sky-600">{{ $sellerStats['verified'] }}</p>
        </article>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Seller</th>
                        <th class="px-3 py-2">Contact</th>
                        <th class="px-3 py-2">Listings</th>
                        <th class="px-3 py-2">Engagement</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sellers as $seller)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-3">
                                <p class="font-semibold text-slate-900">{{ $seller->name }}</p>
                                <p class="text-xs text-slate-500">Joined {{ $seller->created_at->diffForHumans() }}</p>
                                <p class="text-xs text-slate-500">{{ $seller->city ?: '—' }}{{ $seller->state ? ', '.$seller->state : '' }}</p>
                            </td>
                            <td class="px-3 py-3">
                                @php
                                    $verificationStatus = $seller->seller_verification_status ?: 'unsubmitted';
                                    $verificationPalette = match ($verificationStatus) {
                                        'approved' => 'bg-emerald-100 text-emerald-700',
                                        'pending' => 'bg-amber-100 text-amber-700',
                                        'rejected' => 'bg-rose-100 text-rose-700',
                                        default => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp

                                <p class="text-xs text-slate-700">{{ $seller->email }}</p>
                                <p class="text-xs text-slate-500">{{ $seller->phone ?: 'No phone' }}</p>
                                <p class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $verificationPalette }}">
                                    {{ $verificationStatus }}
                                </p>
                            </td>
                            <td class="px-3 py-3 text-xs text-slate-700">
                                <p><span class="font-semibold">Total:</span> {{ $seller->listings_count }}</p>
                                <p><span class="font-semibold">Approved:</span> {{ $seller->approved_listings_count }}</p>
                                <p><span class="font-semibold">Pending:</span> {{ $seller->pending_listings_count }}</p>
                                <p><span class="font-semibold">Sold:</span> {{ $seller->sold_listings_count }}</p>
                            </td>
                            <td class="px-3 py-3 text-xs text-slate-700">
                                <p><span class="font-semibold">Chats:</span> {{ $seller->seller_chats_count }}</p>
                                <p><span class="font-semibold">Push devices:</span> {{ $seller->active_push_devices_count }}</p>
                                <p><span class="font-semibold">Last seen:</span> {{ $seller->last_seen_at?->diffForHumans() ?? 'N/A' }}</p>
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $seller->is_blocked ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $seller->is_blocked ? 'Blocked' : 'Active' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.sellers.show', $seller) }}" class="rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Manage</a>

                                    <form method="POST" action="{{ route('admin.sellers.toggle-block', $seller) }}">
                                        @csrf
                                        <button type="submit" class="rounded-xl {{ $seller->is_blocked ? 'bg-emerald-600' : 'bg-rose-600' }} px-3 py-1.5 text-xs font-semibold text-white">
                                            {{ $seller->is_blocked ? 'Unblock' : 'Block' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.sellers.test-push', $seller) }}">
                                        @csrf
                                        <button type="submit" class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">Test Push</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-center text-slate-600">No sellers found for selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $sellers->links() }}
        </div>
    </section>
@endsection
