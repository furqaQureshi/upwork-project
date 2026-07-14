<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="font-display text-2xl font-bold text-slate-900">AutoIQ</h1>
                <p class="text-sm text-slate-600">AI operating dashboard for car and vehicle dealers</p>
            </div>
            <a href="{{ route('listings.index') }}" class="app-btn-muted">My Listings</a>
        </div>
    </x-slot>

    @php
        $inventory = (array) ($dashboard['inventory'] ?? []);
        $leads = (array) ($dashboard['leads'] ?? []);
        $pricing = (array) ($dashboard['pricing_recommendations'] ?? []);
        $highlights = (array) ($dashboard['inventory_highlights'] ?? []);
        $videoIdeas = (array) ($dashboard['video_ideas'] ?? []);
    @endphp

    <div class="space-y-5">
        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="app-card">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Inventory</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ (int) ($inventory['total'] ?? 0) }}</p>
                <p class="mt-1 text-xs text-slate-500">Live {{ (int) ($inventory['live'] ?? 0) }} | Pending {{ (int) ($inventory['pending'] ?? 0) }}</p>
            </article>
            <article class="app-card">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Sold Units</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ (int) ($inventory['sold'] ?? 0) }}</p>
                <p class="mt-1 text-xs text-slate-500">Tracks closed inventory momentum</p>
            </article>
            <article class="app-card">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Lead Conversations</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ (int) ($leads['total_conversations'] ?? 0) }}</p>
                <p class="mt-1 text-xs text-slate-500">Unread {{ (int) ($leads['unread_messages'] ?? 0) }}</p>
            </article>
            <article class="app-card">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Response Rate</p>
                <p class="mt-1 text-2xl font-black text-slate-900">{{ (int) ($leads['response_rate_percent'] ?? 0) }}%</p>
                <p class="mt-1 text-xs text-slate-500">Seller reply efficiency</p>
            </article>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <div class="app-card">
                <h2 class="font-display text-lg font-bold text-slate-900">Pricing Recommendations</h2>
                @if ($pricing === [])
                    <p class="mt-3 text-sm text-slate-500">No vehicle listings available yet for AI pricing guidance.</p>
                @else
                    <div class="mt-3 space-y-3">
                        @foreach ($pricing as $item)
                            <div class="rounded-2xl border border-slate-200 p-3">
                                <p class="text-sm font-semibold text-slate-900">{{ $item['title'] }}</p>
                                <p class="mt-1 text-xs text-slate-600">
                                    Current: ₹{{ number_format((float) ($item['current_price'] ?? 0)) }}
                                    | Suggested: ₹{{ number_format((float) ($item['suggested_price'] ?? 0)) }}
                                </p>
                                <p class="mt-1 text-[11px] text-slate-500">Range: ₹{{ number_format((float) ($item['min_price'] ?? 0)) }} - ₹{{ number_format((float) ($item['max_price'] ?? 0)) }}</p>
                                <a href="{{ route('listings.show', $item['slug']) }}" class="mt-2 inline-flex text-xs font-semibold text-orange-600 hover:underline">Open Listing</a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="app-card">
                <h2 class="font-display text-lg font-bold text-slate-900">Lead & Inventory Insights</h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 p-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Ageing 0-7 days</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ (int) (($inventory['ageing']['0_7'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Ageing 8-30 days</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ (int) (($inventory['ageing']['8_30'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Ageing 31-90 days</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ (int) (($inventory['ageing']['31_90'] ?? 0)) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-3">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Ageing 90+ days</p>
                        <p class="mt-1 text-xl font-bold text-slate-900">{{ (int) (($inventory['ageing']['90_plus'] ?? 0)) }}</p>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-orange-200 bg-orange-50 p-3">
                    <p class="text-sm font-semibold text-orange-800">Average inventory price</p>
                    <p class="mt-1 text-xl font-black text-orange-900">₹{{ number_format((float) ($inventory['average_price'] ?? 0)) }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-2">
            <div class="app-card">
                <h2 class="font-display text-lg font-bold text-slate-900">Top Performing Listings</h2>
                @if ($highlights === [])
                    <p class="mt-3 text-sm text-slate-500">No inventory highlights available yet.</p>
                @else
                    <div class="mt-3 space-y-2">
                        @foreach ($highlights as $item)
                            <div class="rounded-xl border border-slate-200 px-3 py-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $item['title'] }}</p>
                                <p class="text-xs text-slate-500">Views: {{ (int) ($item['views'] ?? 0) }} | {{ ucfirst((string) ($item['status'] ?? '')) }} | {{ $item['city'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="app-card">
                <h2 class="font-display text-lg font-bold text-slate-900">AI Video Ideas</h2>
                @if ($videoIdeas === [])
                    <p class="mt-3 text-sm text-slate-500">No vehicle video prompts yet.</p>
                @else
                    <div class="mt-3 space-y-2">
                        @foreach ($videoIdeas as $idea)
                            <div class="rounded-xl border border-slate-200 px-3 py-2">
                                <p class="text-sm font-semibold text-slate-900">{{ $idea['title'] }}</p>
                                <p class="text-xs text-slate-600">{{ $idea['script'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>
