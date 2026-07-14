<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->with('parent:id,name')
            ->withCount('listings')
            ->withCount('children')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'parentCategories' => Category::query()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:categories,name'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'icon_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'condition_enabled' => ['nullable', 'boolean'],
        ]);

        $iconPath = null;

        if ($request->hasFile('icon_file')) {
            $iconPath = $request->file('icon_file')->store('categories/icons', 'public');
        }

        Category::create([
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => $validated['name'],
            'slug' => $this->makeUniqueSlug($validated['name']),
            'icon' => $iconPath,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active', true),
            'condition_enabled' => $request->boolean('condition_enabled', true),
        ]);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category created successfully.');
    }

    public function show(Category $category): RedirectResponse
    {
        return redirect()->route('admin.categories.edit', $category);
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', [
            'category' => $category,
            'parentCategories' => Category::query()
                ->whereNull('parent_id')
                ->where('id', '!=', $category->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('categories', 'name')->ignore($category->id),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'icon_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_icon' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', 'boolean'],
            'condition_enabled' => ['nullable', 'boolean'],
        ]);

        $parentId = isset($validated['parent_id']) ? (int) $validated['parent_id'] : null;

        if ($parentId === $category->id) {
            return back()->withErrors([
                'parent_id' => 'A category cannot be its own parent.',
            ])->withInput();
        }

        if ($parentId !== null && $this->isDescendantOf($parentId, $category->id)) {
            return back()->withErrors([
                'parent_id' => 'Invalid parent category selected.',
            ])->withInput();
        }

        $iconPath = $category->icon;

        if ($request->boolean('remove_icon')) {
            $this->deleteIconIfStored($iconPath);
            $iconPath = null;
        }

        if ($request->hasFile('icon_file')) {
            $this->deleteIconIfStored($iconPath);
            $iconPath = $request->file('icon_file')->store('categories/icons', 'public');
        }

        $category->update([
            'parent_id' => $parentId,
            'name' => $validated['name'],
            'slug' => $this->makeUniqueSlug($validated['name'], $category->id),
            'icon' => $iconPath,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
            'condition_enabled' => $request->boolean('condition_enabled', true),
        ]);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->listings()->exists()) {
            return back()->with('status', 'Cannot delete category with existing listings.');
        }

        if ($category->children()->exists()) {
            return back()->with('status', 'Cannot delete category while it has subcategories.');
        }

        $this->deleteIconIfStored($category->icon);

        $category->delete();

        return back()->with('status', 'Category removed successfully.');
    }

    private function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'category';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            Category::query()
                ->where('slug', $slug)
                ->when($ignoreId, function ($builder) use ($ignoreId): void {
                    $builder->where('id', '!=', $ignoreId);
                })
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function deleteIconIfStored(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function isDescendantOf(int $possibleParentId, int $currentCategoryId): bool
    {
        $cursor = Category::query()->find($possibleParentId);

        while ($cursor && $cursor->parent_id !== null) {
            if ((int) $cursor->parent_id === $currentCategoryId) {
                return true;
            }

            $cursor = Category::query()->find((int) $cursor->parent_id);
        }

        return false;
    }
}
