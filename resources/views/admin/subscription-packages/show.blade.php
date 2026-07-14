@extends('admin.layout')

@section('title', 'Subscription Package Details')

@section('content')
    <section class="mx-auto max-w-4xl rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-center gap-3">
                @if ($package->icon_url)
                    <img src="{{ $package->icon_url }}" alt="{{ $package->name }}" class="h-14 w-14 rounded-xl border border-slate-200 object-cover">
                @else
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-xl bg-slate-100 text-slate-500">
                        <x-heroicon :name="$package->package_type === 'featured' ? 'tag' : ($package->package_type === 'story' ? 'play-circle' : 'clipboard-document-list')" class="h-7 w-7" />
                    </span>
                @endif
                <div>
                    <h2 class="font-display text-2xl font-bold text-slate-900">{{ $package->name }}</h2>
                    <p class="text-sm text-slate-600">{{ $package->package_type_label }}</p>
                    @if ($package->is_seller_verification)
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-amber-700">{{ $package->seller_tier_label }} • {{ $package->resolved_seller_badge_label }}</p>
                    @endif
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('admin.subscription-packages.edit', $package) }}" class="app-btn-primary">Edit</a>
                <a href="{{ route('admin.subscription-packages.index') }}" class="app-btn-muted">Back</a>
            </div>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Price</p>
                <p class="mt-1 text-lg font-bold text-slate-900">Rs {{ number_format((float) $package->price, 2) }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Discount</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format((float) $package->discount_percent, 2) }}%</p>
            </div>
            <div class="rounded-2xl bg-emerald-50 p-3">
                <p class="text-xs uppercase tracking-wide text-emerald-700">Final Price</p>
                <p class="mt-1 text-lg font-bold text-emerald-700">Rs {{ number_format((float) $package->final_price, 2) }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Package Duration</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ $package->package_duration_label }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Item Limit</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ $package->item_limit_label }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3">
                <p class="text-xs uppercase tracking-wide text-slate-500">Listing Duration</p>
                <p class="mt-1 text-lg font-bold text-slate-900">{{ $package->listing_duration_label }}</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Category Scope</p>
                @if ($package->package_type === 'featured')
                    <p class="mt-1 font-semibold text-slate-900">Global (All Categories)</p>
                @elseif ($package->package_type === 'story')
                    <p class="mt-1 font-semibold text-slate-900">Global (Verified Seller Stories)</p>
                @elseif ($package->category_scope === 'specific')
                    <p class="mt-1 font-semibold text-slate-900">Specific: {{ $package->category?->display_name ?? '-' }}</p>
                @else
                    <p class="mt-1 font-semibold text-slate-900">Global (All Categories)</p>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Status</p>
                <p class="mt-1 font-semibold {{ $package->is_active ? 'text-emerald-700' : 'text-slate-600' }}">{{ $package->is_active ? 'Active' : 'Inactive' }}</p>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4 sm:col-span-2">
                <p class="text-xs uppercase tracking-wide text-slate-500">Seller Verification</p>
                @if ($package->is_seller_verification)
                    <p class="mt-1 font-semibold text-slate-900">{{ $package->seller_tier_label }}</p>
                    <p class="text-sm text-slate-600">Badge: {{ $package->resolved_seller_badge_label }}</p>
                @else
                    <p class="mt-1 text-sm text-slate-600">This package is not used for seller verification.</p>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-200 p-4 sm:col-span-2">
                <p class="text-xs uppercase tracking-wide text-slate-500">Call Access</p>
                <p class="mt-1 inline-flex items-center gap-2 font-semibold {{ $package->allows_call ? 'text-orange-700' : 'text-slate-600' }}">
                    <x-heroicon name="phone" class="h-4 w-4" />
                    {{ $package->allows_call ? 'Enabled for buyers who purchase this package' : 'Not included in this package' }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 p-4 sm:col-span-2">
                <p class="text-xs uppercase tracking-wide text-slate-500">AI Access</p>
                <p class="mt-1 font-semibold {{ $package->allows_ai ? 'text-cyan-700' : 'text-slate-600' }}">
                    {{ $package->allows_ai
                        ? 'Enabled ('.$package->ai_usage_limit_label.' credits)'
                        : 'Not included in this package' }}
                </p>
            </div>
        </div>

        <div class="mt-5 rounded-2xl border border-slate-200 p-4">
            <p class="text-xs uppercase tracking-wide text-slate-500">Key Points</p>
            @if (! empty($package->key_points))
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-700">
                    @foreach ($package->key_points as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            @else
                <p class="mt-2 text-sm text-slate-600">No key points defined.</p>
            @endif
        </div>

        @if ($package->is_seller_verification)
            <div class="mt-5 rounded-2xl border border-slate-200 p-4">
                <p class="text-xs uppercase tracking-wide text-slate-500">Required Documents</p>
                @if (! empty($package->required_documents))
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($package->required_documents as $document)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ strtoupper(str_replace('_', ' ', $document)) }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-600">No required documents defined.</p>
                @endif
            </div>
        @endif
    </section>
@endsection
