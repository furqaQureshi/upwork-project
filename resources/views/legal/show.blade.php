<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-orange-600">Legal</p>
            <h1 class="font-display text-2xl font-bold text-slate-900">{{ $pageTitle }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $pageSummary }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="app-card">
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <article class="whitespace-pre-line text-sm leading-7 text-slate-700">{{ $pageContent }}</article>
            </div>
        </section>

        <section class="app-card">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Related legal pages</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($pages as $page)
                        <a
                            href="{{ $page['url'] }}"
                            class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-semibold transition {{ $pageSlug === $page['slug'] ? 'border-orange-500 bg-orange-500 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-orange-300 hover:text-orange-700' }}"
                        >
                            {{ $page['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
