@extends('admin.layout')

@section('title', 'Free Post Limits')

@section('content')
    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-bold text-slate-900">Free Post Limits</h2>
                <p class="mt-0.5 text-sm text-slate-500">
                    Restrict how many free ads a user can post per category within a rolling time window.
                    Category-specific rules take priority over the global (All Categories) rule.
                </p>
            </div>
            <a href="{{ route('admin.settings.index') }}?tab=listings"
               class="app-btn-muted text-sm">← Back to Settings</a>
        </div>

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        {{-- Add new rule --}}
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-display text-lg font-bold text-slate-900">Add Rule</h3>
            <p class="mt-0.5 text-sm text-slate-500">
                Leave Category blank to create a global rule that applies to every category.
            </p>

            <form method="POST" action="{{ route('admin.free-post-limits.store') }}" class="mt-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="settings-label" for="new_category_id">Category (optional)</label>
                        <select id="new_category_id" name="category_id" class="app-select mt-1">
                            <option value="">All Categories (global)</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected((string) old('category_id') === (string) $cat->id)>
                                    {{ $cat->display_name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                    </div>
                    <div>
                        <label class="settings-label" for="new_window_days">Window (days)</label>
                        <input id="new_window_days" type="number" name="window_days"
                               value="{{ old('window_days', 30) }}"
                               min="1" max="3650"
                               class="app-input mt-1"
                               placeholder="e.g. 30">
                        <p class="mt-1 text-xs text-slate-500">Rolling look-back period in days.</p>
                        <x-input-error :messages="$errors->get('window_days')" class="mt-1" />
                    </div>
                    <div>
                        <label class="settings-label" for="new_limit_count">Max ads allowed</label>
                        <input id="new_limit_count" type="number" name="limit_count"
                               value="{{ old('limit_count', 1) }}"
                               min="1" max="9999"
                               class="app-input mt-1"
                               placeholder="e.g. 1">
                        <p class="mt-1 text-xs text-slate-500">How many ads can be posted in the window.</p>
                        <x-input-error :messages="$errors->get('limit_count')" class="mt-1" />
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="app-btn-primary">Add Rule</button>
                </div>
            </form>
        </section>

        {{-- Existing rules table --}}
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-display text-lg font-bold text-slate-900">Active Rules
                <span class="ml-2 text-base font-semibold text-slate-400">({{ $limits->count() }})</span>
            </h3>

            @if ($limits->isEmpty())
                <p class="mt-4 text-sm text-slate-500">No rules defined. All users can post unlimited free ads until you add a rule.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-2">Category</th>
                                <th class="px-3 py-2">Window</th>
                                <th class="px-3 py-2">Max Ads</th>
                                <th class="px-3 py-2">Summary</th>
                                <th class="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($limits as $limit)
                                <tr class="border-b border-slate-100" x-data="{ editing: false }">
                                    <td class="px-3 py-3">
                                        @if ($limit->category_id === null)
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-600">All Categories</span>
                                        @else
                                            <span class="font-semibold text-slate-800">{{ $limit->scope_label }}</span>
                                        @endif
                                    </td>

                                    {{-- View mode --}}
                                    <td class="px-3 py-3" x-show="!editing">{{ $limit->window_days }} days</td>
                                    <td class="px-3 py-3" x-show="!editing">{{ $limit->limit_count }} ad(s)</td>
                                    <td class="px-3 py-3 text-xs text-slate-500" x-show="!editing">
                                        {{ $limit->limit_count }} ad{{ $limit->limit_count !== 1 ? 's' : '' }}
                                        per {{ $limit->window_days }}-day period
                                    </td>

                                    {{-- Edit mode --}}
                                    <td class="px-3 py-2" x-show="editing" x-cloak>
                                        <form id="edit-form-{{ $limit->id }}"
                                              method="POST"
                                              action="{{ route('admin.free-post-limits.update', $limit) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="number" name="window_days"
                                                   value="{{ $limit->window_days }}"
                                                   min="1" max="3650"
                                                   class="app-input w-24"
                                                   required>
                                        </form>
                                    </td>
                                    <td class="px-3 py-2" x-show="editing" x-cloak>
                                        <input type="number" name="limit_count"
                                               form="edit-form-{{ $limit->id }}"
                                               value="{{ $limit->limit_count }}"
                                               min="1" max="9999"
                                               class="app-input w-20"
                                               required>
                                    </td>
                                    <td class="px-3 py-2" x-show="editing" x-cloak></td>

                                    {{-- Action buttons --}}
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            {{-- Edit / Save toggle --}}
                                            <button type="button"
                                                    @click="editing = !editing"
                                                    x-text="editing ? 'Cancel' : 'Edit'"
                                                    class="text-xs font-semibold text-orange-600 hover:underline">
                                            </button>

                                            <button type="submit"
                                                    form="edit-form-{{ $limit->id }}"
                                                    x-show="editing" x-cloak
                                                    class="text-xs font-semibold text-emerald-600 hover:underline">
                                                Save
                                            </button>

                                            {{-- Delete --}}
                                            <form method="POST"
                                                  action="{{ route('admin.free-post-limits.destroy', $limit) }}"
                                                  onsubmit="return confirm('Delete this rule?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="text-xs font-semibold text-rose-600 hover:underline">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- How it works --}}
        <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4 text-sm text-sky-800">
            <p class="font-bold">How rules are applied</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                <li>When a user posts a new ad, the most <strong>specific matching rule</strong> for their chosen category is applied first.</li>
                <li>If no category-specific rule exists, the <strong>global (All Categories)</strong> rule is used.</li>
                <li>If no rule matches at all, posting is <strong>unlimited</strong> (unless blocked by a subscription package requirement).</li>
                <li>Users with an active listing subscription package bypass these free limits entirely.</li>
                <li>The window is a <strong>rolling period</strong> — it looks back N days from the moment of posting.</li>
            </ul>
        </div>

    </div>
@endsection
