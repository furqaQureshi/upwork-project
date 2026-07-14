@extends('admin.layout')

@section('title', 'Seller Profile')

@section('content')
    @php
        $verificationStatus = $seller->seller_verification_status ?: 'unsubmitted';
        $verificationPalette = match ($verificationStatus) {
            'approved' => 'bg-emerald-100 text-emerald-700',
            'pending' => 'bg-amber-100 text-amber-700',
            'rejected' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
        $documentUrl = $seller->verification_document_url;
    @endphp

    <div class="grid gap-5 lg:grid-cols-3">
        <section class="space-y-4 lg:col-span-2">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-display text-2xl font-bold text-slate-900">{{ $seller->name }}</h2>
                            {{-- Car Seller Verification Badge --}}
                            <x-verified-car-seller-badge :user="$seller" size="md" />
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $seller->email }}{{ $seller->phone ? ' • '.$seller->phone : '' }}</p>
                        <p class="text-xs text-slate-500">{{ $seller->city ?: 'City N/A' }}{{ $seller->state ? ', '.$seller->state : '' }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $seller->is_blocked ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                        {{ $seller->is_blocked ? 'Blocked' : 'Active' }}
                    </span>
                </div>

                <div class="mt-4 grid gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 px-3 py-2">
                        <p class="text-[11px]">Joined</p>
                        <p class="mt-1 text-sm font-bold text-slate-800">{{ $seller->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-3 py-2">
                        <p class="text-[11px]">Last Seen</p>
                        <p class="mt-1 text-sm font-bold text-slate-800">{{ $seller->last_seen_at?->diffForHumans() ?? 'N/A' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-3 py-2">
                        <p class="text-[11px]">KYC Status</p>
                        <p class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $verificationPalette }}">{{ $verificationStatus }}</p>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.sellers.toggle-block', $seller) }}">
                        @csrf
                        <button type="submit" class="rounded-xl {{ $seller->is_blocked ? 'bg-emerald-600' : 'bg-rose-600' }} px-4 py-2 text-sm font-semibold text-white">
                            {{ $seller->is_blocked ? 'Unblock Seller' : 'Block Seller' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.sellers.test-push', $seller) }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Send Test Push</button>
                    </form>

                    <a href="{{ route('admin.users.index', ['q' => $seller->email]) }}" class="rounded-xl bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Open in Users</a>
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-display text-lg font-bold text-slate-900">Document Verification Review</h3>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold uppercase tracking-wide {{ $verificationPalette }}">{{ $verificationStatus }}</span>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 px-3 py-2.5 text-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Document Type</p>
                        <p class="mt-1 font-semibold text-slate-800">{{ $seller->verification_document_type ?: 'Not submitted' }}</p>
                    </div>
                    <div class="rounded-2xl bg-slate-50 px-3 py-2.5 text-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Document Number</p>
                        <p class="mt-1 font-semibold text-slate-800">{{ $seller->verification_document_number ?: 'Not submitted' }}</p>
                    </div>
                </div>

                @if ($documentUrl)
                    <a href="{{ $documentUrl }}" target="_blank" rel="noopener" class="mt-4 inline-flex rounded-xl bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Open Submitted Document</a>
                @else
                    <p class="mt-4 text-sm text-slate-600">Seller has not uploaded a verification document yet.</p>
                @endif

                @if ($seller->seller_verification_note)
                    <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                        Last rejection note: {{ $seller->seller_verification_note }}
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.sellers.verification.approve', $seller) }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Approve Verification</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('admin.sellers.verification.reject', $seller) }}" class="mt-4 space-y-3">
                    @csrf
                    <x-input-label for="verification_reject_reason" value="Reject with reason" />
                    <textarea id="verification_reject_reason" name="reason" class="app-textarea" required>{{ old('reason') }}</textarea>
                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Reject Verification</button>
                </form>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <form method="GET" action="{{ route('admin.sellers.show', $seller) }}" class="grid gap-3 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Search Listings</label>
                        <input id="q" type="text" name="q" value="{{ request('q') }}" class="app-input" placeholder="Title or city">
                    </div>
                    <div>
                        <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>
                        <select id="status" name="status" class="app-select">
                            <option value="">All</option>
                            @foreach (['pending', 'approved', 'rejected', 'sold'] as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="app-btn-primary">Apply</button>
                        <a href="{{ route('admin.sellers.show', $seller) }}" class="app-btn-muted">Reset</a>
                    </div>
                </form>
            </article>

            <section class="space-y-3">
                @forelse ($listings as $listing)
                    <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-display text-xl font-bold text-slate-900">{{ $listing->title }}</h3>
                                <p class="text-sm text-slate-600">{{ $listing->city }} • {{ $listing->category->name }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $listing->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($listing->status === 'pending' ? 'bg-amber-100 text-amber-700' : ($listing->status === 'rejected' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700')) }}">
                                {{ ucfirst($listing->status) }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <span>₹{{ number_format((float) $listing->price) }}</span>
                            <span>•</span>
                            <span>{{ $listing->views }} views</span>
                            <span>•</span>
                            <span>{{ $listing->open_reports_count }} open reports</span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="{{ route('admin.listings.show', $listing) }}" class="rounded-xl bg-slate-800 px-3 py-2 text-xs font-semibold text-white">Inspect</a>

                            @if ($listing->status !== 'approved')
                                <form method="POST" action="{{ route('admin.listings.approve', $listing) }}">
                                    @csrf
                                    <button type="submit" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">Approve</button>
                                </form>
                            @endif

                            @if ($listing->status !== 'rejected')
                                <form method="POST" action="{{ route('admin.listings.reject', $listing) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="text" name="reason" class="app-input h-10 text-xs" placeholder="Rejection reason" required>
                                    <button type="submit" class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white">Reject</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('admin.listings.featured', $listing) }}">
                                @csrf
                                <button type="submit" class="rounded-xl bg-orange-500 px-3 py-2 text-xs font-semibold text-white">
                                    {{ $listing->is_featured ? 'Unfeature' : 'Feature' }}
                                </button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 text-sm text-slate-600">No listings found for this seller and filters.</div>
                @endforelse

                <div>
                    {{ $listings->links() }}
                </div>
            </section>
        </section>

        <aside class="space-y-4">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-display text-lg font-bold text-slate-900">Seller Snapshot</h3>
                <dl class="mt-3 space-y-2 text-sm text-slate-700">
                    <div class="flex items-center justify-between gap-2">
                        <dt>Total listings</dt>
                        <dd class="font-semibold">{{ $seller->listings_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt>Approved</dt>
                        <dd class="font-semibold text-emerald-700">{{ $seller->approved_listings_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt>Pending</dt>
                        <dd class="font-semibold text-amber-700">{{ $seller->pending_listings_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt>Rejected</dt>
                        <dd class="font-semibold text-rose-700">{{ $seller->rejected_listings_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt>Sold</dt>
                        <dd class="font-semibold">{{ $seller->sold_listings_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt>Chats</dt>
                        <dd class="font-semibold">{{ $seller->seller_chats_count }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <dt>Push devices</dt>
                        <dd class="font-semibold">{{ $seller->active_push_devices_count }}</dd>
                    </div>
                </dl>
            </article>
        </aside>
    </div>
@endsection
