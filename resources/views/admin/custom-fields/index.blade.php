@extends('admin.layout')

@section('title', 'Custom Fields')

@section('content')
    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-bold text-slate-900">Category Custom Fields</h2>
                <p class="text-sm text-slate-600">Manage dynamic form fields for categories and subcategories.</p>
            </div>
            <a href="{{ route('admin.custom-fields.create') }}" class="app-btn-primary">Add Custom Field</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Field</th>
                        <th class="px-3 py-2">Category</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Required</th>
                        <th class="px-3 py-2">Active</th>
                        <th class="px-3 py-2">Sort</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customFields as $field)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-2">
                                    @if ($field->icon_url)
                                        <img src="{{ $field->icon_url }}" alt="{{ $field->name }}" class="h-8 w-8 rounded-lg border border-slate-200 object-cover">
                                    @else
                                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-500">CF</span>
                                    @endif
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $field->name }}</p>
                                        @if ($field->parent_field_id)
                                            <p class="text-xs text-orange-600 font-medium">↳ Sub-field of: {{ $field->parentField?->name ?? 'Unknown' }}</p>
                                        @elseif (is_array($field->options) && count($field->options) > 0)
                                            <p class="text-xs text-slate-500">{{ count($field->options) }} option(s)</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-slate-600">
                                @if ($field->category)
                                    {{ $field->category->display_name }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ strtoupper($field->field_type) }}</span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $field->is_required ? 'bg-orange-100 text-orange-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $field->is_required ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $field->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $field->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-slate-600">{{ $field->sort_order }}</td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.custom-fields.edit', $field) }}" class="rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Edit</a>
                                    <form method="POST" action="{{ route('admin.custom-fields.destroy', $field) }}" onsubmit="return confirm('Delete this custom field?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-4 text-center text-slate-600">No custom fields found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customFields->links() }}
        </div>
    </section>
@endsection
