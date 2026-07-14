@extends('admin.layout')

@section('title', 'Listings Moderation')

@section('content')
    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('admin.listings.index') }}" class="grid gap-3 md:grid-cols-4">
            <div>
                <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Search</label>
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
            <div class="flex items-end gap-2 md:col-span-2">
                <button type="submit" class="app-btn-primary">Apply</button>
                <a href="{{ route('admin.listings.index') }}" class="app-btn-muted">Reset</a>
            </div>
        </form>

        <!-- Export Button -->
        <div class="mt-4">
            <a href="{{ route('admin.listings.export', request()->query()) }}" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                <i class="fas fa-download"></i> Export CSV
            </a>
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form id="bulk-delete-form" method="POST" action="{{ route('admin.listings.bulk-destroy') }}" class="grid gap-3 md:grid-cols-4" onsubmit="return confirm('Delete selected listings? This action is permanent.');">
            @csrf
            <div class="md:col-span-3">
                <label for="bulk-delete-reason" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Bulk Delete Reason (Optional)</label>
                <input id="bulk-delete-reason" type="text" name="reason" value="{{ old('reason') }}" class="app-input" maxlength="300" placeholder="Reason sent in seller notification">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Delete Selected</button>
            </div>
        </form>

        <label class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
            <input id="select-all-listings" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500">
            Select all listings on this page
        </label>
    </section>

    <section class="space-y-3">
        @forelse ($listings as $listing)
            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="truncate font-display text-xl font-bold text-slate-900">{{ $listing->title }}</h3>
                        <p class="text-sm text-slate-600">{{ $listing->user->name }} • {{ $listing->city }} • {{ $listing->category->name }}</p>
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
                    <label class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700">
                        <input
                            type="checkbox"
                            name="listing_ids[]"
                            value="{{ $listing->id }}"
                            form="bulk-delete-form"
                            class="h-4 w-4 rounded border-slate-300 text-orange-600 focus:ring-orange-500"
                            data-listing-bulk-checkbox
                        >
                        Select
                    </label>

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

                    <form method="POST" action="{{ route('admin.listings.destroy', $listing) }}" onsubmit="return confirm('Delete this listing permanently?');">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="reason" value="Deleted by admin moderation.">
                        <button type="submit" class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-slate-200 bg-white p-5 text-sm text-slate-600">No listings found for selected filters.</div>
        @endforelse

        <div>
            {{ $listings->links() }}
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAllCheckbox = document.getElementById('select-all-listings');
            const listingCheckboxes = Array.from(document.querySelectorAll('[data-listing-bulk-checkbox]'));

            if (!selectAllCheckbox || listingCheckboxes.length === 0) {
                return;
            }

            const syncSelectAll = function () {
                selectAllCheckbox.checked = listingCheckboxes.every(function (checkbox) {
                    return checkbox.checked;
                });
            };

            selectAllCheckbox.addEventListener('change', function () {
                listingCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });

            listingCheckboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', syncSelectAll);
            });

            syncSelectAll();
        });
    </script>
@endsection
