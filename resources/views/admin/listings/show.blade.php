@extends('admin.layout')

@section('title', 'Listing Inspection')

@section('content')
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="space-y-4 lg:col-span-2">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-display text-2xl font-bold text-slate-900">{{ $listing->title }}</h2>
                <p class="mt-1 text-sm text-slate-600">Seller: {{ $listing->user->name }} • {{ $listing->user->email }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <span class="rounded-full bg-slate-100 px-2 py-1">{{ ucfirst($listing->status) }}</span>
                    <span>₹{{ number_format((float) $listing->price) }}</span>
                    <span>{{ $listing->city }}{{ $listing->state ? ', '.$listing->state : '' }}</span>
                    <span>{{ $listing->views }} views</span>
                </div>

                <p class="mt-4 whitespace-pre-line text-sm text-slate-700">{{ $listing->description }}</p>

                @if ($listing->rejection_reason)
                    <div class="mt-4 rounded-2xl bg-rose-50 p-3 text-sm text-rose-700">
                        Rejection reason: {{ $listing->rejection_reason }}
                    </div>
                @endif
            </article>

            @if ($listing->images->isNotEmpty())
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-display text-lg font-bold text-slate-900">Images</h3>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($listing->images as $image)
                            <img src="{{ $image->url }}" alt="Listing image" class="h-28 w-full rounded-2xl object-cover">
                        @endforeach
                    </div>
                </article>
            @endif

            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-display text-lg font-bold text-slate-900">Moderation Actions</h3>
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.listings.approve', $listing) }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Approve Listing</button>
                    </form>

                    <form method="POST" action="{{ route('admin.listings.featured', $listing) }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white">
                            {{ $listing->is_featured ? 'Remove Featured' : 'Set Featured' }}
                        </button>
                    </form>
                </div>

                <form method="POST" action="{{ route('admin.listings.reject', $listing) }}" class="mt-4 space-y-3">
                    @csrf
                    <x-input-label for="reason" value="Reject with reason" />
                    <textarea id="reason" name="reason" class="app-textarea" required>{{ old('reason') }}</textarea>
                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white">Reject Listing</button>
                </form>

                <form method="POST" action="{{ route('admin.listings.destroy', $listing) }}" class="mt-4 space-y-3" onsubmit="return confirm('Delete this listing permanently?');">
                    @csrf
                    @method('DELETE')
                    <x-input-label for="delete_reason" value="Delete reason (optional)" />
                    <textarea id="delete_reason" name="reason" class="app-textarea" maxlength="300">{{ old('reason') }}</textarea>
                    <button type="submit" class="rounded-xl bg-rose-700 px-4 py-2 text-sm font-semibold text-white">Delete Listing</button>
                </form>
            </article>
        </section>

        <aside class="space-y-4">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-display text-lg font-bold text-slate-900">Reports</h3>
                <div class="mt-3 space-y-3">
                    @forelse ($listing->reports as $report)
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $report->status }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ $report->reason }}</p>
                            @if ($report->details)
                                <p class="mt-1 text-sm text-slate-600">{{ $report->details }}</p>
                            @endif
                            <p class="mt-2 text-xs text-slate-500">By {{ $report->user->name }}</p>

                            @if ($report->status === 'open')
                                <div class="mt-2 flex gap-2">
                                    <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white">Resolve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.reports.dismiss', $report) }}">
                                        @csrf
                                        <button type="submit" class="rounded-lg bg-slate-700 px-2.5 py-1.5 text-xs font-semibold text-white">Dismiss</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-600">No reports on this listing.</p>
                    @endforelse
                </div>
            </article>
        </aside>
    </div>
@endsection
