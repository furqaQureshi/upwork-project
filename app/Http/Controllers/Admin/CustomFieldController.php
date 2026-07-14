<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    public function index(): View
    {
        $customFields = CustomField::query()
            ->with(['category.parent', 'parentField:id,name'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25);

        return view('admin.custom-fields.index', [
            'customFields' => $customFields,
        ]);
    }

    public function create(): View
    {
        return view('admin.custom-fields.create', [
            'categories'          => $this->categoryOptions(),
            'types'               => CustomField::FIELD_TYPES,
            'parentFieldOptions'  => $this->parentFieldOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id'     => ['required', 'integer', 'exists:categories,id'],
            'parent_field_id' => ['nullable', 'integer', 'exists:custom_fields,id'],
            'name'            => ['required', 'string', 'max:120'],
            'field_type'      => ['required', 'string', 'in:'.implode(',', CustomField::FIELD_TYPES)],
            'min_length'      => ['nullable', 'integer', 'min:0', 'max:5000'],
            'max_length'      => ['nullable', 'integer', 'min:1', 'max:5000', 'gte:min_length'],
            'options'         => ['nullable', 'string', 'max:10000'],
            'icon_file'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'sort_order'      => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_required'     => ['nullable', 'boolean'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

            $parentFieldId = isset($validated['parent_field_id']) && $validated['parent_field_id']
                ? (int) $validated['parent_field_id']
                : null;

        if ($parentFieldId) {
            $parentField = CustomField::find($parentFieldId);
            if (! $parentField || $parentField->category_id !== (int) $validated['category_id']) {
                return back()->withErrors(['parent_field_id' => 'Parent field must belong to the same category.'])->withInput();
            }
            if (! in_array($parentField->field_type, ['dropdown', 'radio'], true)) {
                return back()->withErrors(['parent_field_id' => 'Parent field must be a dropdown or radio type.'])->withInput();
            }
        }

        $options = $this->parseOptions((string) ($validated['options'] ?? ''), (bool) $parentFieldId);

        if ($this->isChoiceType($validated['field_type']) && empty($options)) {
            return back()->withErrors([
                'options' => 'At least one option is required for radio, dropdown, or checkbox fields.',
            ])->withInput();
        }

        $iconPath = null;

        if ($request->hasFile('icon_file')) {
            $iconPath = $request->file('icon_file')->store('custom-fields/icons', 'public');
        }

        CustomField::create([
            'category_id'     => (int) $validated['category_id'],
            'parent_field_id' => $parentFieldId,
            'name'            => $validated['name'],
            'slug'            => $this->makeUniqueSlug($validated['name'], (int) $validated['category_id']),
            'field_type'      => $validated['field_type'],
            'min_length'      => $validated['min_length'] ?? null,
            'max_length'      => $validated['max_length'] ?? null,
            'options'         => $this->isChoiceType($validated['field_type']) ? $options : null,
            'icon'            => $iconPath,
            'sort_order'      => $validated['sort_order'] ?? 0,
            'is_required'     => $request->boolean('is_required'),
            'is_active'       => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.custom-fields.index')
            ->with('status', 'Custom field created successfully.');
    }

    public function edit(CustomField $customField): View
    {
        return view('admin.custom-fields.edit', [
            'customField'        => $customField,
            'categories'         => $this->categoryOptions(),
            'types'              => CustomField::FIELD_TYPES,
            'parentFieldOptions' => $this->parentFieldOptions($customField->id),
        ]);
    }

    public function update(Request $request, CustomField $customField): RedirectResponse
    {
        $validated = $request->validate([
            'category_id'     => ['required', 'integer', 'exists:categories,id'],
            'parent_field_id' => ['nullable', 'integer', 'exists:custom_fields,id'],
            'name'            => ['required', 'string', 'max:120'],
            'field_type'      => ['required', 'string', 'in:'.implode(',', CustomField::FIELD_TYPES)],
            'min_length'      => ['nullable', 'integer', 'min:0', 'max:5000'],
            'max_length'      => ['nullable', 'integer', 'min:1', 'max:5000', 'gte:min_length'],
            'options'         => ['nullable', 'string', 'max:10000'],
            'icon_file'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_icon'     => ['nullable', 'boolean'],
            'sort_order'      => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_required'     => ['nullable', 'boolean'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

            $parentFieldId = isset($validated['parent_field_id']) && $validated['parent_field_id']
                ? (int) $validated['parent_field_id']
                : null;

        // Prevent self-reference or circular parent
        if ($parentFieldId && $parentFieldId === $customField->id) {
            return back()->withErrors(['parent_field_id' => 'A field cannot be its own parent.'])->withInput();
        }

        if ($parentFieldId) {
            $parentField = CustomField::find($parentFieldId);
            if (! $parentField || $parentField->category_id !== (int) $validated['category_id']) {
                return back()->withErrors(['parent_field_id' => 'Parent field must belong to the same category.'])->withInput();
            }
            if (! in_array($parentField->field_type, ['dropdown', 'radio'], true)) {
                return back()->withErrors(['parent_field_id' => 'Parent field must be a dropdown or radio type.'])->withInput();
            }
        }

        $options = $this->parseOptions((string) ($validated['options'] ?? ''), (bool) $parentFieldId);

        if ($this->isChoiceType($validated['field_type']) && empty($options)) {
            return back()->withErrors([
                'options' => 'At least one option is required for radio, dropdown, or checkbox fields.',
            ])->withInput();
        }

        $iconPath = $customField->icon;

        if ($request->boolean('remove_icon')) {
            $this->deleteFileIfExists($iconPath);
            $iconPath = null;
        }

        if ($request->hasFile('icon_file')) {
            $this->deleteFileIfExists($iconPath);
            $iconPath = $request->file('icon_file')->store('custom-fields/icons', 'public');
        }

        $customField->update([
            'category_id'     => (int) $validated['category_id'],
            'parent_field_id' => $parentFieldId,
            'name'            => $validated['name'],
            'slug'            => $this->makeUniqueSlug($validated['name'], (int) $validated['category_id'], $customField->id),
            'field_type'      => $validated['field_type'],
            'min_length'      => $validated['min_length'] ?? null,
            'max_length'      => $validated['max_length'] ?? null,
            'options'         => $this->isChoiceType($validated['field_type']) ? $options : null,
            'icon'            => $iconPath,
            'sort_order'      => $validated['sort_order'] ?? 0,
            'is_required'     => $request->boolean('is_required'),
            'is_active'       => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.custom-fields.index')
            ->with('status', 'Custom field updated successfully.');
    }

    public function destroy(CustomField $customField): RedirectResponse
    {
        $this->deleteFileIfExists($customField->icon);

        foreach ($customField->values as $value) {
            if ($customField->field_type === 'file') {
                $this->deleteFileIfExists($value->value_text);
            }
        }

        $customField->delete();

        return back()->with('status', 'Custom field removed successfully.');
    }

    private function categoryOptions()
    {
        return Category::query()
            ->with('parent:id,name')
            ->where('is_active', true)
            ->orderByRaw('COALESCE(parent_id, id)')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function parseOptions(string $raw, bool $isSubField = false): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $lines = array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $raw) ?: []));

        // Nested options are valid only for dependent sub-fields.
        if ($isSubField) {
            $nested = [];
            foreach ($lines as $line) {
                if (! preg_match('/^([^:]+):\s*(.+)$/', $line, $m)) {
                    continue;
                }
                $parent   = trim($m[1]);
                $children = array_values(array_filter(array_map('trim', explode('|', $m[2]))));
                if ($parent === '' || $children === []) {
                    continue;
                }
                $nested[$parent] = $children;
            }
            return $nested;
        }

        // Flat format: split by newlines and/or commas
        $parts   = preg_split('/[\r\n,]+/', $raw) ?: [];
        $options = [];

        foreach ($parts as $part) {
            $option = trim((string) $part);
            if ($option === '') {
                continue;
            }
            $options[] = $option;
        }

        return array_values(array_unique($options));
    }

    private function parentFieldOptions(?int $excludeId = null): \Illuminate\Database\Eloquent\Collection
    {
        return CustomField::query()
            ->whereIn('field_type', ['dropdown', 'radio'])
            ->whereNull('parent_field_id')
            ->where('is_active', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->with('category:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function isChoiceType(string $type): bool
    {
        return in_array($type, ['radio', 'dropdown', 'checkbox'], true);
    }

    private function deleteFileIfExists(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function makeUniqueSlug(string $name, int $categoryId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'field';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            CustomField::query()
                ->where('category_id', $categoryId)
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
}
