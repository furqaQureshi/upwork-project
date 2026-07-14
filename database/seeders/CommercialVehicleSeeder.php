<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CommercialVehicleSeeder extends Seeder
{
    public function run(): void
    {
        $existing = Category::where('name', 'Commercial Vehicles')->first();

        $category = Category::updateOrCreate(
            ['name' => 'Commercial Vehicles'],
            [
                'parent_id' => null,
                'slug' => $this->uniqueCategorySlug('Commercial Vehicles', $existing?->id),
                'icon' => 'truck',
                'sort_order' => 9,
                'is_active' => true,
                'condition_enabled' => true,
            ]
        );

        $categoryId = (int) $category->id;

        CustomField::where('category_id', $categoryId)
            ->whereIn('slug', [
                'brand',
                'model',
                'variant',
                'km-run',
                'insurance-status',
                'permit-status',
                'fitness-status',
            ])
            ->get()
            ->each(function (CustomField $field): void {
                $field->delete();
            });

        $brandField = CustomField::create([
            'category_id' => $categoryId,
            'parent_field_id' => null,
            'name' => 'Brand',
            'slug' => $this->uniqueCustomFieldSlug('brand', $categoryId),
            'field_type' => 'dropdown',
            'options' => array_keys($this->modelsByBrand()),
            'sort_order' => 10,
            'is_required' => false,
            'is_active' => true,
        ]);

        $modelField = CustomField::create([
            'category_id' => $categoryId,
            'parent_field_id' => $brandField->id,
            'name' => 'Model',
            'slug' => $this->uniqueCustomFieldSlug('model', $categoryId),
            'field_type' => 'dropdown',
            'options' => $this->modelsByBrand(),
            'sort_order' => 20,
            'is_required' => false,
            'is_active' => true,
        ]);

        CustomField::create([
            'category_id' => $categoryId,
            'parent_field_id' => $modelField->id,
            'name' => 'Variant',
            'slug' => $this->uniqueCustomFieldSlug('variant', $categoryId),
            'field_type' => 'dropdown',
            'options' => $this->variantsByModel(),
            'sort_order' => 30,
            'is_required' => false,
            'is_active' => true,
        ]);

        CustomField::create([
            'category_id' => $categoryId,
            'parent_field_id' => null,
            'name' => 'KM Run',
            'slug' => $this->uniqueCustomFieldSlug('km-run', $categoryId),
            'field_type' => 'number',
            'min_length' => 1,
            'max_length' => 7,
            'sort_order' => 40,
            'is_required' => false,
            'is_active' => true,
        ]);

        CustomField::create([
            'category_id' => $categoryId,
            'parent_field_id' => null,
            'name' => 'Insurance Status',
            'slug' => $this->uniqueCustomFieldSlug('insurance-status', $categoryId),
            'field_type' => 'dropdown',
            'options' => ['Valid', 'Expired', 'Third Party', 'Comprehensive', 'No Insurance'],
            'sort_order' => 50,
            'is_required' => false,
            'is_active' => true,
        ]);

        CustomField::create([
            'category_id' => $categoryId,
            'parent_field_id' => null,
            'name' => 'Permit Status',
            'slug' => $this->uniqueCustomFieldSlug('permit-status', $categoryId),
            'field_type' => 'dropdown',
            'options' => ['Valid', 'Expired', 'Applied', 'Not Required'],
            'sort_order' => 60,
            'is_required' => false,
            'is_active' => true,
        ]);

        CustomField::create([
            'category_id' => $categoryId,
            'parent_field_id' => null,
            'name' => 'Fitness Status',
            'slug' => $this->uniqueCustomFieldSlug('fitness-status', $categoryId),
            'field_type' => 'dropdown',
            'options' => ['Valid', 'Expired', 'Due Soon', 'Not Applicable'],
            'sort_order' => 70,
            'is_required' => false,
            'is_active' => true,
        ]);

        $this->command->info("Commercial Vehicles fields seeded for '{$category->name}' (ID: {$categoryId}).");
    }

    private function modelsByBrand(): array
    {
        return [
            'Tata Motors' => ['Ace Gold', 'Yodha Pickup', 'Intra V30', '407 Gold SFC', '709g LPT', '912 LPK', '1613 LPT'],
            'Ashok Leyland' => ['Dost+', 'BADA DOST i2', 'PARTNER 6 Tyre', 'Ecomet 1015', 'BOSS 1415'],
            'Mahindra' => ['Jeeto', 'Bolero Pickup', 'Supro Maxitruck', 'Furio 7', 'Blazo X 28'],
            'Eicher' => ['Pro 2049', 'Pro 2095XP', 'Pro 3015', 'Pro 6048'],
            'BharatBenz' => ['1015R', '1217R', '1415R', '2823R'],
            'SML Isuzu' => ['Sartaj GS 5252', 'Samrat XT Plus'],
            'Force' => ['Traveller Delivery Van', 'Trax Delivery Van'],
            'Piaggio' => ['Ape Xtra LDX', 'Ape E Xtra FX'],
            'Isuzu' => ['D-Max Regular Cab', 'D-Max S-CAB'],
            'Maruti Suzuki Commercial' => ['Super Carry Petrol', 'Super Carry CNG'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function variantsByModel(): array
    {
        $variants = [];

        foreach ($this->modelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultVariants($model);
            }
        }

        $overrides = [
            'Ace Gold' => ['Petrol', 'Diesel', 'CNG', 'Bi-Fuel'],
            'Yodha Pickup' => ['4x2', '4x4', 'Single Cab', 'Crew Cab'],
            'Intra V30' => ['BS6 Diesel', 'Top'],
            'Dost+' => ['LS', 'LE', 'LX'],
            'BADA DOST i2' => ['i2 LS', 'i2 LX', 'i2 LE'],
            'Jeeto' => ['Petrol', 'Diesel', 'CNG'],
            'Bolero Pickup' => ['ExtraLong', 'CBC', 'City Pickup'],
            'Super Carry Petrol' => ['Cab Chassis', 'Deck'],
            'Super Carry CNG' => ['Cab Chassis', 'Deck'],
            'Other / Custom' => ['Base', 'Mid', 'Top'],
        ];

        foreach ($overrides as $model => $options) {
            $variants[$model] = $options;
        }

        return $variants;
    }

    private function defaultVariants(string $model): array
    {
        if (preg_match('/Pickup|Cab|Carry|Dost|Jeeto|Ace|Intra/i', $model)) {
            return ['Diesel', 'CNG', 'Top'];
        }

        if (preg_match('/LPK|LPT|R$/i', $model)) {
            return ['6 Tyre', '10 Tyre', '12 Tyre'];
        }

        return ['Standard', 'Top', 'Fleet'];
    }

    private function uniqueCustomFieldSlug(string $base, int $categoryId): string
    {
        $slug = Str::slug($base);
        $counter = 2;

        while (CustomField::where('category_id', $categoryId)->where('slug', $slug)->exists()) {
            $slug = Str::slug($base).'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $name, ?int $ignoreId = null): string
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
}
