@php
    $totalCategories = $allCategories->count();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="font-display text-2xl font-bold text-slate-900">All Categories</h1>
            <p class="text-sm text-slate-600">Explore every category and subcategory</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="app-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm font-semibold text-slate-700">{{ $totalCategories }} active categories</p>
                <a href="{{ route('home') }}" class="app-btn-muted">Back to Home</a>
            </div>
        </section>

        @if ($parentCategories->isEmpty())
            <section class="app-card text-center">
                <p class="text-slate-600">No categories available right now.</p>
            </section>
        @else
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($parentCategories as $parent)
                    <article class="app-card space-y-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100">
                                @if ($parent->icon_url)
                                    <img src="{{ $parent->icon_url }}" alt="{{ $parent->name }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-5 w-5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 10h16" />
                                    </svg>
                                @endif
                            </span>

                            <div class="min-w-0">
                                <h2 class="font-display text-lg font-bold text-slate-900">{{ $parent->name }}</h2>
                                <a href="{{ route('categories.show', ['category' => $parent->slug]) }}" class="text-xs font-bold uppercase tracking-wide text-orange-600">Browse with filters</a>
                            </div>
                        </div>

                        @if ($parent->children->isNotEmpty())
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($parent->children as $child)
                                    <a href="{{ route('categories.show', ['category' => $child->slug]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-orange-300 hover:text-orange-600">
                                        {{ $child->name }}
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-slate-500">No subcategories yet.</p>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</x-app-layout>