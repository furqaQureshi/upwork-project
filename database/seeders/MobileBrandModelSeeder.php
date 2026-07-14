<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MobileBrandModelSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::where('name', 'Mobiles')->first()
            ?? Category::where('name', 'Mobile')->first()
            ?? Category::where('slug', 'mobiles')->first();

        if (! $category) {
            $this->command->warn('Mobiles category not found. Skipping MobileBrandModelSeeder.');
            return;
        }

        $catId = $category->id;

        // Remove existing mobile brand/model/storage fields to avoid duplicates
        CustomField::where('category_id', $catId)
            ->whereIn('slug', ['brand', 'model', 'storage'])
            ->get()
            ->each(function (CustomField $f): void { $f->delete(); });

        // 1. Brand field (flat dropdown)
        $brandField = CustomField::create([
            'category_id'     => $catId,
            'parent_field_id' => null,
            'name'            => 'Brand',
            'slug'            => $this->uniqueSlug('brand', $catId),
            'field_type'      => 'dropdown',
            'options'         => $this->brandList(),
            'sort_order'      => 10,
            'is_required'     => false,
            'is_active'       => true,
        ]);

        // 2. Model field (nested dropdown — child of Brand)
        CustomField::create([
            'category_id'     => $catId,
            'parent_field_id' => $brandField->id,
            'name'            => 'Model',
            'slug'            => $this->uniqueSlug('model', $catId),
            'field_type'      => 'dropdown',
            'options'         => $this->modelsByBrand(),
            'sort_order'      => 20,
            'is_required'     => false,
            'is_active'       => true,
        ]);

        // 3. Storage field (flat dropdown — independent)
        CustomField::create([
            'category_id'     => $catId,
            'parent_field_id' => null,
            'name'            => 'Storage',
            'slug'            => $this->uniqueSlug('storage', $catId),
            'field_type'      => 'dropdown',
            'options'         => ['32 GB', '64 GB', '128 GB', '256 GB', '512 GB', '1 TB'],
            'sort_order'      => 30,
            'is_required'     => false,
            'is_active'       => true,
        ]);

        $this->command->info("Mobile Brand/Model/Storage custom fields created for '{$category->name}' (ID: {$catId}).");
    }

    private function brandList(): array
    {
        return [
            'Apple', 'Samsung', 'Xiaomi', 'Realme', 'OnePlus', 'Vivo', 'Oppo',
            'Motorola', 'Nokia', 'Google', 'iQOO', 'Poco', 'Nothing', 'Lava',
            'Infinix', 'Tecno', 'HMD', 'Other',
        ];
    }

    private function modelsByBrand(): array
    {
        return [
            'Apple' => [
                'iPhone 16 Pro Max', 'iPhone 16 Pro', 'iPhone 16 Plus', 'iPhone 16',
                'iPhone 15 Pro Max', 'iPhone 15 Pro', 'iPhone 15 Plus', 'iPhone 15',
                'iPhone 14 Pro Max', 'iPhone 14 Pro', 'iPhone 14 Plus', 'iPhone 14',
                'iPhone 13 Pro Max', 'iPhone 13 Pro', 'iPhone 13', 'iPhone 13 Mini',
                'iPhone 12 Pro Max', 'iPhone 12 Pro', 'iPhone 12', 'iPhone 12 Mini',
                'iPhone SE (3rd Gen)', 'iPhone SE (2nd Gen)', 'iPhone 11 Pro Max',
                'iPhone 11 Pro', 'iPhone 11',
            ],
            'Samsung' => [
                'Galaxy S25 Ultra', 'Galaxy S25+', 'Galaxy S25',
                'Galaxy S24 Ultra', 'Galaxy S24+', 'Galaxy S24 FE', 'Galaxy S24',
                'Galaxy S23 Ultra', 'Galaxy S23+', 'Galaxy S23 FE', 'Galaxy S23',
                'Galaxy A56 5G', 'Galaxy A36 5G', 'Galaxy A26 5G', 'Galaxy A16 5G',
                'Galaxy A55 5G', 'Galaxy A35 5G', 'Galaxy A25 5G', 'Galaxy A15 5G',
                'Galaxy M55 5G', 'Galaxy M35 5G', 'Galaxy M15 5G',
                'Galaxy F55 5G', 'Galaxy F15 5G',
            ],
            'Xiaomi' => [
                'Xiaomi 14 Ultra', 'Xiaomi 14 Pro', 'Xiaomi 14',
                'Xiaomi 13T Pro', 'Xiaomi 13T', 'Xiaomi 13 Pro', 'Xiaomi 13',
                'Redmi Note 14 Pro+ 5G', 'Redmi Note 14 Pro 5G', 'Redmi Note 14 5G',
                'Redmi Note 13 Pro+ 5G', 'Redmi Note 13 Pro 5G', 'Redmi Note 13 5G',
                'Redmi 14C', 'Redmi 13C', 'Redmi 12C', 'Redmi 13',
                'Poco X7 Pro 5G', 'Poco X7 5G', 'Poco X6 Pro 5G', 'Poco X6 5G',
                'Poco M7 Pro 5G', 'Poco M6 Pro 5G', 'Poco C75 5G', 'Poco C65',
                'Poco F6 Pro', 'Poco F6',
            ],
            'Realme' => [
                'Realme GT 7 Pro', 'Realme GT 6T', 'Realme GT 6',
                'Realme GT Neo 6 SE', 'Realme GT Neo 6', 'Realme GT Neo 5',
                'Realme 13 Pro+ 5G', 'Realme 13 Pro 5G', 'Realme 13 5G',
                'Realme 12 Pro+ 5G', 'Realme 12 Pro 5G', 'Realme 12 5G',
                'Realme Narzo 70 Turbo 5G', 'Realme Narzo 70 Pro 5G', 'Realme Narzo 70 5G',
                'Realme C67 5G', 'Realme C65 5G', 'Realme C65', 'Realme C63',
            ],
            'OnePlus' => [
                'OnePlus 13 5G', 'OnePlus 13R 5G',
                'OnePlus 12 5G', 'OnePlus 12R 5G',
                'OnePlus 11 5G', 'OnePlus 11R 5G',
                'OnePlus Nord 4 5G', 'OnePlus Nord CE 4 5G', 'OnePlus Nord CE 4 Lite 5G',
                'OnePlus Nord CE 3 Lite 5G', 'OnePlus Nord 3 5G',
                'OnePlus Open 5G',
            ],
            'Vivo' => [
                'Vivo X200 Pro', 'Vivo X200 Pro Mini', 'Vivo X200',
                'Vivo X100 Ultra', 'Vivo X100 Pro', 'Vivo X100',
                'Vivo V40 Pro', 'Vivo V40', 'Vivo V40 SE',
                'Vivo V30 Pro', 'Vivo V30', 'Vivo V30e',
                'Vivo Y300 Plus 5G', 'Vivo Y300 5G', 'Vivo Y200 5G',
                'Vivo T3 Ultra 5G', 'Vivo T3 Pro 5G', 'Vivo T3x 5G',
            ],
            'Oppo' => [
                'Oppo Find X8 Pro', 'Oppo Find X8', 'Oppo Find X7 Ultra',
                'Oppo Reno 13 Pro 5G', 'Oppo Reno 13 5G',
                'Oppo Reno 12 Pro 5G', 'Oppo Reno 12 5G', 'Oppo Reno 12F 5G',
                'Oppo F27 Pro+ 5G', 'Oppo F27 Pro 5G', 'Oppo F27 5G',
                'Oppo A3x 5G', 'Oppo A3 Pro 5G', 'Oppo A60',
            ],
            'Motorola' => [
                'Motorola Edge 50 Ultra 5G', 'Motorola Edge 50 Pro 5G',
                'Motorola Edge 50 Fusion 5G', 'Motorola Edge 50 5G',
                'Motorola Edge 40 Pro 5G', 'Motorola Edge 40 Neo',
                'Motorola Moto G85 5G', 'Motorola Moto G75 5G',
                'Motorola Moto G64 5G', 'Motorola Moto G54 5G',
                'Motorola Razr 50 Ultra 5G', 'Motorola Razr 50 5G',
                'Motorola Moto E14', 'Motorola Moto G04',
            ],
            'Nokia' => [
                'Nokia G42 5G', 'Nokia G22', 'Nokia G21', 'Nokia C32',
                'Nokia X30 5G', 'Nokia G60 5G', 'Nokia XR21', 'Nokia C21 Plus',
                'Nokia 2660 Flip', 'Nokia 3310 (2017)',
            ],
            'Google' => [
                'Google Pixel 9 Pro XL', 'Google Pixel 9 Pro Fold',
                'Google Pixel 9 Pro', 'Google Pixel 9',
                'Google Pixel 9a',
                'Google Pixel 8a', 'Google Pixel 8 Pro', 'Google Pixel 8',
                'Google Pixel Fold', 'Google Pixel 7 Pro', 'Google Pixel 7',
            ],
            'iQOO' => [
                'iQOO 13 5G', 'iQOO 12 5G', 'iQOO 11 5G',
                'iQOO Neo 9 Pro 5G', 'iQOO Neo 9 5G', 'iQOO Neo 8 Pro 5G',
                'iQOO Z9s Pro 5G', 'iQOO Z9 Turbo+ 5G', 'iQOO Z9 Turbo 5G',
                'iQOO Z9 5G', 'iQOO Z7 Pro 5G', 'iQOO Z7 5G',
            ],
            'Poco' => [
                'Poco F6 Pro 5G', 'Poco F6 5G', 'Poco F5 Pro 5G', 'Poco F5 5G',
                'Poco X7 Pro 5G', 'Poco X7 5G', 'Poco X6 Pro 5G',
                'Poco M7 Pro 5G', 'Poco M6 Pro 5G',
                'Poco C75 5G', 'Poco C65', 'Poco C61',
            ],
            'Nothing' => [
                'Nothing Phone (3a) Pro', 'Nothing Phone (3a)',
                'Nothing Phone (2a) Plus', 'Nothing Phone (2a)',
                'Nothing Phone (2)', 'Nothing Phone (1)',
            ],
            'Lava' => [
                'Lava Agni 3 5G', 'Lava Agni 2 5G', 'Lava Blaze 3 5G',
                'Lava Blaze 2 5G', 'Lava Yuva 3 Pro', 'Lava Storm 5G',
            ],
            'Infinix' => [
                'Infinix GT 20 Pro', 'Infinix Zero 40 5G',
                'Infinix Note 40 Pro+ 5G', 'Infinix Note 40 Pro 5G', 'Infinix Note 40 5G',
                'Infinix Hot 50 Pro+ 5G', 'Infinix Hot 50 5G',
                'Infinix Smart 8 Plus', 'Infinix Smart 8',
            ],
            'Tecno' => [
                'Tecno Spark 30 Pro 5G', 'Tecno Spark 30 5G',
                'Tecno Camon 30 Pro 5G', 'Tecno Camon 30 5G',
                'Tecno Pova 6 Pro 5G', 'Tecno Pova 6 5G',
                'Tecno Pop 9 5G', 'Tecno Phantom V Fold 5G',
            ],
            'HMD' => [
                'HMD Skyline', 'HMD Pulse Pro', 'HMD Pulse', 'HMD Fusion',
                'HMD Crest', 'HMD Vibe', 'HMD Smart',
            ],
            'Other' => [
                'Other / Custom',
            ],
        ];
    }

    private function uniqueSlug(string $base, int $categoryId): string
    {
        $slug = Str::slug($base);
        $counter = 2;

        while (CustomField::where('category_id', $categoryId)->where('slug', $slug)->exists()) {
            $slug = Str::slug($base) . '-' . $counter++;
        }

        return $slug;
    }
}
