@extends('admin.layout')

@section('title', 'Subscription Packages')

@section('content')
    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-bold text-slate-900">Subscription Management</h2>
                <p class="text-sm text-slate-600">Manage listing, featured, story, and seller verification packages.</p>
            </div>
            <a href="{{ route('admin.subscription-packages.create') }}" class="app-btn-primary">Create Package</a>
        </div>

        <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-3">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.subscription-packages.index') }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ ($typeFilter ?? 'all') === 'all' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border border-slate-200' }}">
                    All
                </a>
                <a href="{{ route('admin.subscription-packages.index', ['type' => 'listing']) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ ($typeFilter ?? 'all') === 'listing' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border border-slate-200' }}">
                    Listing
                </a>
                <a href="{{ route('admin.subscription-packages.index', ['type' => 'featured']) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ ($typeFilter ?? 'all') === 'featured' ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 border border-slate-200' }}">
                    Featured
                </a>
                <a href="{{ route('admin.subscription-packages.index', ['type' => 'story']) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ ($typeFilter ?? 'all') === 'story' ? 'bg-violet-700 text-white' : 'bg-white text-violet-700 border border-violet-200' }}">
                    Stories
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Package</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Seller Tier</th>
                        <th class="px-3 py-2">Price</th>
                        <th class="px-3 py-2">Discount</th>
                        <th class="px-3 py-2">Final Price</th>
                        <th class="px-3 py-2">Duration</th>
                        <th class="px-3 py-2">Call Access</th>
                        <th class="px-3 py-2">AI Access</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packages as $package)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    @if ($package->icon_url)
                                        <img src="{{ $package->icon_url }}" alt="{{ $package->name }}" class="h-9 w-9 rounded-lg border border-slate-200 object-cover">
                                    @else
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                            <x-heroicon :name="$package->package_type === 'featured' ? 'tag' : ($package->package_type === 'story' ? 'play-circle' : 'clipboard-document-list')" class="h-5 w-5" />
                                        </span>
                                    @endif
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $package->name }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ $package->package_type === 'listing' ? ($package->category_scope === 'specific' ? ($package->category?->display_name ?? 'Specific Category') : 'Global categories') : ($package->package_type === 'story' ? 'Stories unlock for verified sellers' : 'All categories') }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-slate-700">
                                @if ($package->package_type === 'story')
                                    <span class="rounded-full bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-700">{{ $package->package_type_label }}</span>
                                @else
                                    {{ $package->package_type_label }}
                                @endif
                            </td>
                            <td class="px-3 py-3 text-slate-700">
                                @if ($package->is_seller_verification)
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">{{ $package->seller_tier_label }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-slate-700">Rs {{ number_format((float) $package->price, 2) }}</td>
                            <td class="px-3 py-3 text-slate-700">{{ number_format((float) $package->discount_percent, 2) }}%</td>
                            <td class="px-3 py-3 font-semibold text-emerald-700">Rs {{ number_format((float) $package->final_price, 2) }}</td>
                            <td class="px-3 py-3 text-slate-700">{{ $package->package_duration_label }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold {{ $package->allows_call ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-500' }}">
                                    <x-heroicon name="phone" class="h-3.5 w-3.5" />
                                    {{ $package->allows_call ? 'Enabled' : 'Off' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold {{ $package->allows_ai ? 'bg-cyan-100 text-cyan-700' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $package->allows_ai ? $package->ai_usage_limit_label : 'Off' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $package->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $package->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.subscription-packages.show', $package) }}" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">View</a>
                                    <a href="{{ route('admin.subscription-packages.edit', $package) }}" class="rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-3 py-4 text-center text-slate-600">No subscription packages found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $packages->appends(request()->query())->links() }}
        </div>
    </section>
@endsection
