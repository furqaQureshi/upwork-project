@extends('admin.layout')

@section('title', 'Categories')

@section('content')
    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-display text-2xl font-bold text-slate-900">Marketplace Categories</h2>
                <p class="text-sm text-slate-600">Organize listings with active category groups.</p>
            </div>
            <a href="{{ route('admin.categories.create') }}" class="app-btn-primary">Add Category</a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Icon</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Parent</th>
                        <th class="px-3 py-2">Slug</th>
                        <th class="px-3 py-2">Listings</th>
                        <th class="px-3 py-2">Subcategories</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr class="border-b border-slate-100">
                            <td class="px-3 py-3">
                                @if ($category->icon_url)
                                    <img src="{{ $category->icon_url }}" alt="{{ $category->name }}" class="h-9 w-9 rounded-lg border border-slate-200 object-cover">
                                @else
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-xs font-bold text-slate-500">CAT</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 font-semibold text-slate-900">{{ $category->name }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $category->parent?->name ?? '-' }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $category->slug }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $category->listings_count }}</td>
                            <td class="px-3 py-3 text-slate-600">{{ $category->children_count }}</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $category->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $category->is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.categories.edit', $category) }}" class="rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white">Edit</a>
                                    <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-4 text-center text-slate-600">No categories found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $categories->links() }}
        </div>
    </section>
@endsection
