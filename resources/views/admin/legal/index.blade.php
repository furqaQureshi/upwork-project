@extends('admin.layout')

@section('title', 'Legal Content')

@section('content')
    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3">
            <p class="text-sm font-semibold text-rose-700">Please fix the highlighted legal content fields.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-rose-600">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.legal-content.update') }}" class="space-y-5">
        @csrf

        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-display text-lg font-bold text-slate-900">Google Play Legal Pages</h2>
            <p class="mt-1 text-sm text-slate-500">Manage Terms, Privacy, and related policy pages. All pages are public and can be submitted in Google Play Console URLs.</p>
        </div>

        @foreach ($pages as $page)
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-display text-base font-bold text-slate-900">{{ $page['label'] }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $page['summary'] }}</p>
                    </div>
                    <a href="{{ $page['route'] }}" target="_blank" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                        Open public page
                    </a>
                </div>

                <div class="mt-4 grid gap-4">
                    <div>
                        <label class="settings-label" for="{{ $page['title_key'] }}">Page title</label>
                        <input
                            id="{{ $page['title_key'] }}"
                            type="text"
                            name="{{ $page['title_key'] }}"
                            value="{{ old($page['title_key'], $page['title']) }}"
                            class="app-input mt-1"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div>
                        <label class="settings-label" for="{{ $page['content_key'] }}">Page content</label>
                        <textarea
                            id="{{ $page['content_key'] }}"
                            name="{{ $page['content_key'] }}"
                            rows="16"
                            class="app-textarea mt-1"
                            maxlength="65000"
                            required
                        >{{ old($page['content_key'], $page['content']) }}</textarea>
                        <p class="mt-1 text-xs text-slate-500">Use plain text. Paragraph and line breaks are preserved automatically on the public page.</p>
                    </div>
                </div>
            </section>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="app-btn-primary">Save Legal Content</button>
        </div>
    </form>
@endsection
