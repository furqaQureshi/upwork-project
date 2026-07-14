<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FreePostLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FreePostLimitController extends Controller
{
    public function index(): View
    {
        $limits = FreePostLimit::query()
            ->with('category:id,name,parent_id')
            ->orderByRaw('category_id IS NULL DESC')   // global rules first
            ->orderBy('category_id')
            ->orderBy('window_days')
            ->get();

        $categories = Category::query()
            ->with('parent:id,name')
            ->where('is_active', true)
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.free-post-limits.index', [
            'limits' => $limits,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'window_days'  => ['required', 'integer', 'min:1', 'max:3650'],
            'limit_count'  => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $categoryId = $validated['category_id'] ? (int) $validated['category_id'] : null;

        $exists = FreePostLimit::query()
            ->where('window_days', $validated['window_days'])
            ->where(fn ($q) => $categoryId
                ? $q->where('category_id', $categoryId)
                : $q->whereNull('category_id'))
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->with('error', 'A rule with the same category and window already exists. Edit or delete it first.');
        }

        FreePostLimit::create([
            'category_id' => $categoryId,
            'window_days' => $validated['window_days'],
            'limit_count' => $validated['limit_count'],
        ]);

        return back()->with('status', 'Post limit rule added.');
    }

    public function update(Request $request, FreePostLimit $freePostLimit): RedirectResponse
    {
        $validated = $request->validate([
            'window_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'limit_count' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $freePostLimit->update($validated);

        return back()->with('status', 'Post limit rule updated.');
    }

    public function destroy(FreePostLimit $freePostLimit): RedirectResponse
    {
        $freePostLimit->delete();

        return back()->with('status', 'Post limit rule deleted.');
    }
}
