<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Cars', 'icon' => 'car', 'sort_order' => 1],
            ['name' => 'Bikes', 'icon' => 'bike', 'sort_order' => 2],
            ['name' => 'Mobiles', 'icon' => 'phone', 'sort_order' => 3],
            ['name' => 'Electronics', 'icon' => 'bolt', 'sort_order' => 4],
            ['name' => 'Furniture', 'icon' => 'chair', 'sort_order' => 5],
            ['name' => 'Property', 'icon' => 'building', 'sort_order' => 6],
            ['name' => 'Fashion', 'icon' => 'sparkles', 'sort_order' => 7],
            ['name' => 'Jobs', 'icon' => 'briefcase', 'sort_order' => 8],
            ['name' => 'Commercial Vehicles', 'icon' => 'truck', 'sort_order' => 9],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                [
                    'slug' => Str::slug($category['name']),
                    'icon' => $category['icon'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
