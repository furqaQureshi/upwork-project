<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-3xl font-bold text-slate-900">My Listings</h1>
                <p class="text-sm text-slate-600">Track approvals, edits, and buyer interest.</p>
            </div>
            <a href="{{ route('listings.create') }}" class="app-btn-primary">Post New Ad</a>
        </div>
    </x-slot>

    @php
        $pendingCount = $listings->getCollection()->where('status', 'pending')->count();
        $approvedCount = $listings->getCollection()->where('status', 'approved')->count();
        $soldCount = $listings->getCollection()->where('status', 'sold')->count();
        $inFeedRowsInterval = max(1, (int) setting('adsense_feed_rows_interval', 2));
        $inFeedInsertEvery = $inFeedRowsInterval * 2;
    @endphp

    <div class="space-y-5">
        <section class="grid grid-cols-3 gap-2 sm:gap-3">
            <div class="app-card p-3 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</p>
                <p class="mt-1 font-display text-2xl font-bold text-amber-600 sm:text-3xl">{{ $pendingCount }}</p>
            </div>
            <div class="app-card p-3 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Approved</p>
                <p class="mt-1 font-display text-2xl font-bold text-emerald-600 sm:text-3xl">{{ $approvedCount }}</p>
            </div>
            <div class="app-card p-3 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sold</p>
                <p class="mt-1 font-display text-2xl font-bold text-slate-900 sm:text-3xl">{{ $soldCount }}</p>
            </div>
        </section>

        @if ($listings->isEmpty())
            <section class="app-card text-center">
                <p class="text-slate-600">No listings yet. Create your first ad to start selling.</p>
                <a href="{{ route('listings.create') }}" class="app-btn-primary mt-4">Create Listing</a>
            </section>
        @else
            <section class="home-feed-grid">
                @foreach ($listings as $listing)
                    <x-listing-card :listing="$listing">
                        <x-slot:actions>
                            <div class="grid grid-cols-1 gap-2 min-[420px]:grid-cols-2">
                                <a href="{{ route('listings.show', $listing) }}" class="app-btn-muted w-full text-center text-xs sm:text-sm">View</a>
                                <a href="{{ route('listings.edit', $listing) }}" class="app-btn-muted w-full text-center text-xs sm:text-sm">Edit</a>

                                @if ($listing->status === 'approved')
                                    <a href="{{ route('payments.checkout', $listing) }}" class="app-btn-primary col-span-1 w-full text-center text-xs min-[420px]:col-span-2 sm:text-sm">
                                        {{ $listing->is_featured && (! $listing->featured_until || $listing->featured_until->isFuture()) ? 'Extend Featured Plan' : 'Boost as Featured' }}
                                    </a>
                                @endif

                                @if ($listing->status !== 'sold')
                                    <form method="POST" action="{{ route('listings.mark-sold', $listing) }}" class="col-span-1 min-[420px]:col-span-2">
                                        @csrf
                                        <button type="submit" class="app-btn-primary w-full text-xs sm:text-sm">Mark as Sold</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('listings.destroy', $listing) }}" class="col-span-1 min-[420px]:col-span-2" onsubmit="return confirm('Delete this listing permanently?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl bg-rose-600 px-4 py-2.5 text-xs font-semibold text-white sm:text-sm">Delete Listing</button>
                                </form>
                            </div>
                        </x-slot:actions>
                    </x-listing-card>

                    @if ($loop->iteration % $inFeedInsertEvery === 0 && ! $loop->last)
                        <div class="col-span-2 md:col-span-3 2xl:col-span-4">
                            <x-google-ad location="feed" />
                        </div>
                    @endif
                @endforeach
            </section>

            <div>
                {{ $listings->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
