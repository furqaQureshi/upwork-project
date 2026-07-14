<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomField;
use Illuminate\Database\Seeder;

class PhoneMakeModelMatchTypeSeeder extends Seeder
{
    public function run(): void
    {
        $category = Category::query()
            ->whereIn('name', ['Mobiles', 'Mobile', 'Phones'])
            ->orWhereIn('slug', ['mobiles', 'mobile', 'phones'])
            ->first();

        if (! $category) {
            $this->command?->warn('Phone category not found. Skipping PhoneMakeModelMatchTypeSeeder.');

            return;
        }

        $makeField = $this->upsertDropdownField(
            categoryId: (int) $category->id,
            slug: 'brand',
            name: 'Make',
            options: $this->makeList(),
            sortOrder: 10,
            parentFieldId: null,
        );

        $this->upsertDropdownField(
            categoryId: (int) $category->id,
            slug: 'model',
            name: 'Model',
            options: $this->modelsByMake(),
            sortOrder: 20,
            parentFieldId: (int) $makeField->id,
        );

        // Clean up old match_type field if it exists
        CustomField::query()
            ->where('category_id', (int) $category->id)
            ->where('slug', 'match-type')
            ->delete();

        $this->command?->info("Dynamic phone Make/Model fields seeded for '{$category->name}' (ID: {$category->id}). Models show dynamically based on selected Make.");
    }

    private function upsertDropdownField(
        int $categoryId,
        string $slug,
        string $name,
        array $options,
        int $sortOrder,
        ?int $parentFieldId,
    ): CustomField {
        return CustomField::query()->updateOrCreate(
            [
                'category_id' => $categoryId,
                'slug' => $slug,
            ],
            [
                'parent_field_id' => $parentFieldId,
                'name' => $name,
                'field_type' => 'dropdown',
                'options' => $options,
                'sort_order' => $sortOrder,
                'is_required' => false,
                'is_active' => true,
            ],
        );
    }

    private function makeList(): array
    {
        return [
            'Apple', 'Samsung', 'Xiaomi', 'Realme', 'OnePlus', 'Vivo', 'Oppo',
            'Motorola', 'Nokia', 'Google', 'iQOO', 'Poco', 'Nothing', 'Lava',
            'Infinix', 'Tecno', 'HMD', 'Asus', 'Sony', 'Lenovo', 'Micromax',
            'Huawei', 'ZTE', 'Honor', 'Nothing', 'Fairphone', 'Palm', 'Wise',
            'Transsion', 'Dooee', 'Ulefone', 'Cubot', 'Blackview', 'Other',
        ];
    }

    private function modelsByMake(): array
    {
        return [
            'Apple' => [
                // Latest Flagship
                'iPhone 16 Pro Max', 'iPhone 16 Pro', 'iPhone 16 Plus', 'iPhone 16',
                'iPhone 15 Pro Max', 'iPhone 15 Pro', 'iPhone 15 Plus', 'iPhone 15',
                // Recent Generation
                'iPhone 14 Pro Max', 'iPhone 14 Pro', 'iPhone 14 Plus', 'iPhone 14',
                'iPhone 13 Pro Max', 'iPhone 13 Pro', 'iPhone 13', 'iPhone 13 Mini',
                'iPhone 12 Pro Max', 'iPhone 12 Pro', 'iPhone 12', 'iPhone 12 Mini',
                'iPhone 11 Pro Max', 'iPhone 11 Pro', 'iPhone 11',
                // Older iPhone Models
                'iPhone XS Max', 'iPhone XS', 'iPhone XR', 'iPhone X',
                'iPhone 8 Plus', 'iPhone 8', 'iPhone 7 Plus', 'iPhone 7',
                'iPhone 6s Plus', 'iPhone 6s', 'iPhone 6 Plus', 'iPhone 6',
                'iPhone 5s', 'iPhone 5c', 'iPhone 5',
                // Budget/SE Models
                'iPhone SE (3rd Gen)', 'iPhone SE (2nd Gen)', 'iPhone SE (1st Gen)',
            ],
            'Samsung' => [
                // Latest Flagship
                'Galaxy S25 Ultra', 'Galaxy S25+', 'Galaxy S25',
                'Galaxy S24 Ultra', 'Galaxy S24+', 'Galaxy S24',
                'Galaxy S24 FE', 'Galaxy S23 Ultra', 'Galaxy S23+', 'Galaxy S23',
                // Older S Series
                'Galaxy S22 Ultra', 'Galaxy S22+', 'Galaxy S22',
                'Galaxy S21 Ultra', 'Galaxy S21+', 'Galaxy S21',
                'Galaxy S20 Ultra', 'Galaxy S20+', 'Galaxy S20',
                'Galaxy S10+', 'Galaxy S10', 'Galaxy S10e',
                'Galaxy S9+', 'Galaxy S9', 'Galaxy S8+', 'Galaxy S8',
                'Galaxy S7 Edge', 'Galaxy S7',
                // A Series Budget/Mid-Range
                'Galaxy A56 5G', 'Galaxy A36 5G', 'Galaxy A26 5G',
                'Galaxy A55 5G', 'Galaxy A35 5G', 'Galaxy A25 5G', 'Galaxy A15 5G',
                'Galaxy A54 5G', 'Galaxy A34 5G', 'Galaxy A24',
                'Galaxy A53 5G', 'Galaxy A33 5G', 'Galaxy A23',
                // M Series Budget
                'Galaxy M55 5G', 'Galaxy M35 5G', 'Galaxy M15 5G',
                'Galaxy M54 5G', 'Galaxy M34 5G', 'Galaxy M14',
                // Foldable
                'Galaxy Z Fold 6', 'Galaxy Z Flip 6',
                'Galaxy Z Fold 5', 'Galaxy Z Flip 5',
            ],
            'Xiaomi' => [
                // Latest Flagship
                'Xiaomi 14 Ultra', 'Xiaomi 14 Pro', 'Xiaomi 14',
                'Xiaomi 13 Ultra Pro', 'Xiaomi 13 Pro', 'Xiaomi 13',
                'Xiaomi 12 Ultra', 'Xiaomi 12 Pro', 'Xiaomi 12',
                // Redmi Note Premium
                'Redmi Note 14 Pro+ 5G', 'Redmi Note 14 Pro 5G', 'Redmi Note 14 5G',
                'Redmi Note 13 Pro+ 5G', 'Redmi Note 13 Pro 5G', 'Redmi Note 13 5G',
                'Redmi Note 12 Pro+ 5G', 'Redmi Note 12 Pro 5G', 'Redmi Note 12 5G',
                'Redmi Note 11 Pro+ 5G', 'Redmi Note 11 Pro 5G', 'Redmi Note 11',
                // Redmi Budget
                'Redmi 14C', 'Redmi 13', 'Redmi 12C', 'Redmi 11',
                'Redmi 10A', 'Redmi 9A',
                // Redmi K Series
                'Redmi K70E', 'Redmi K70', 'Redmi K60 Ultra', 'Redmi K60',
            ],
            'Realme' => [
                // GT Series Flagship
                'Realme GT 7 Pro', 'Realme GT 6', 'Realme GT 6T',
                'Realme GT 5 Pro', 'Realme GT 5',
                // Premium Series
                'Realme 13 Pro+ 5G', 'Realme 13 Pro 5G', 'Realme 13 5G',
                'Realme 12 Pro+ 5G', 'Realme 12 Pro 5G', 'Realme 12',
                'Realme 11 Pro+ 5G', 'Realme 11 Pro 5G', 'Realme 11',
                // Narzo Series
                'Realme Narzo 70 Pro 5G', 'Realme Narzo 70 5G',
                'Realme Narzo 60 Pro 5G', 'Realme Narzo 60 5G',
                // Budget Series
                'Realme C67 5G', 'Realme C65', 'Realme C63', 'Realme C61',
                'Realme C55', 'Realme C51', 'Realme C35', 'Realme C33',
            ],
            'OnePlus' => [
                // Latest Flagship
                'OnePlus 13 5G', 'OnePlus 13R 5G',
                'OnePlus 12 5G', 'OnePlus 12R 5G',
                'OnePlus 11 5G', 'OnePlus 11R 5G',
                'OnePlus 10 Pro 5G', 'OnePlus 10T 5G',
                'OnePlus 9 Pro 5G', 'OnePlus 9 5G',
                'OnePlus 8 Pro 5G', 'OnePlus 8 5G',
                // Nord Budget Series
                'OnePlus Nord 4 5G', 'OnePlus Nord CE 4 5G', 'OnePlus Nord 3 5G',
                'OnePlus Nord 2 5G', 'OnePlus Nord 2T 5G', 'OnePlus Nord 2',
                'OnePlus Nord', 'OnePlus Nord CE', 'OnePlus Nord CE 2',
                // Foldable
                'OnePlus Open 5G',
            ],
            'Vivo' => [
                // X Series Flagship
                'Vivo X200 Pro', 'Vivo X200', 'Vivo X100 Pro', 'Vivo X100',
                'Vivo X90 Pro+', 'Vivo X90 Pro', 'Vivo X90',
                'Vivo X80 Pro', 'Vivo X80',
                // V Series Premium
                'Vivo V40 Pro', 'Vivo V40', 'Vivo V39 Pro', 'Vivo V39',
                'Vivo V30 Pro', 'Vivo V30', 'Vivo V29 Pro', 'Vivo V29',
                // Y Series Budget
                'Vivo Y300 5G', 'Vivo Y200 5G', 'Vivo Y200',
                'Vivo Y100 5G', 'Vivo Y100', 'Vivo Y91', 'Vivo Y90',
                // T Series
                'Vivo T3 Ultra 5G', 'Vivo T3 Pro 5G', 'Vivo T3 5G',
                'Vivo T2 Pro 5G', 'Vivo T2 5G', 'Vivo T2x',
            ],
            'Oppo' => [
                // Find Series Flagship
                'Oppo Find X8 Pro', 'Oppo Find X8', 'Oppo Find X7 Ultra',
                'Oppo Find X7', 'Oppo Find X6 Pro', 'Oppo Find X6',
                'Oppo Find X5 Pro', 'Oppo Find X5',
                // Reno Series
                'Oppo Reno 13 Pro 5G', 'Oppo Reno 13 5G',
                'Oppo Reno 12 Pro 5G', 'Oppo Reno 12 5G',
                'Oppo Reno 11 Pro 5G', 'Oppo Reno 11 5G',
                'Oppo Reno 10 Pro 5G', 'Oppo Reno 10 5G',
                // A Series Budget
                'Oppo A3 Pro 5G', 'Oppo A3', 'Oppo A60',
                'Oppo A59 5G', 'Oppo A58 5G', 'Oppo A57 5G',
                // F Series Mid-Range
                'Oppo F27 Pro+ 5G', 'Oppo F27 Pro 5G', 'Oppo F27 5G',
                'Oppo F25 Pro 5G', 'Oppo F25 5G',
            ],
            'Motorola' => [
                // Edge Series Flagship
                'Motorola Edge 50 Ultra 5G', 'Motorola Edge 50 Pro 5G',
                'Motorola Edge 50 Fusion 5G', 'Motorola Edge 50 5G',
                'Motorola Edge 40 Neo', 'Motorola Edge 40 Pro 5G', 'Motorola Edge 40 5G',
                'Motorola Edge 30 Pro', 'Motorola Edge 30 Ultra', 'Motorola Edge 30',
                'Motorola Edge 20 Pro', 'Motorola Edge 20 Fusion',
                // Moto G Series
                'Motorola Moto G85 5G', 'Motorola Moto G75 5G', 'Motorola Moto G64 5G',
                'Motorola Moto G55 5G', 'Motorola Moto G54 5G', 'Motorola Moto G54 Power 5G',
                'Motorola Moto G53 5G', 'Motorola Moto G52', 'Motorola Moto G51',
                // Moto E Series Budget
                'Motorola Moto E14', 'Motorola Moto E13', 'Motorola Moto E12',
                // Razr Series Foldable
                'Motorola Razr 50 Ultra 5G', 'Motorola Razr 50 5G',
                'Motorola Razr 40 Ultra 5G', 'Motorola Razr 40 5G',
            ],
            'Nokia' => [
                // G Series Mid-Range
                'Nokia G42 5G', 'Nokia G41 5G', 'Nokia G40 5G',
                'Nokia G22', 'Nokia G21', 'Nokia G20',
                // C Series Budget
                'Nokia C32', 'Nokia C31', 'Nokia C30', 'Nokia C21 Plus',
                // Feature Phones
                'Nokia 2660 Flip', 'Nokia 8315', 'Nokia 5710 XpressAudio',
                'Nokia 3310 (2017)', 'Nokia 105', 'Nokia 106',
                // Older Series
                'Nokia 1 Plus', 'Nokia 1.3',
            ],
            'Google' => [
                // Pixel Latest
                'Google Pixel 9 Pro XL', 'Google Pixel 9 Pro Fold', 'Google Pixel 9 Pro',
                'Google Pixel 9 Pro a', 'Google Pixel 9', 'Google Pixel 9a',
                // Pixel 8 Series
                'Google Pixel 8 Pro', 'Google Pixel 8', 'Google Pixel 8a',
                // Pixel 7 Series
                'Google Pixel 7 Pro', 'Google Pixel 7', 'Google Pixel 7a',
                // Older Pixels
                'Google Pixel 6 Pro', 'Google Pixel 6', 'Google Pixel 6a',
                'Google Pixel 5a', 'Google Pixel 5', 'Google Pixel 4a 5G',
                // Foldable
                'Google Pixel Fold',
            ],
            'iQOO' => [
                // Latest Flagship
                'iQOO 13 5G', 'iQOO 12 5G', 'iQOO 11 5G', 'iQOO 11 Pro 5G',
                'iQOO 10 Pro 5G', 'iQOO 10 5G',
                // Neo Series
                'iQOO Neo 9 Pro 5G', 'iQOO Neo 9 5G',
                'iQOO Neo 8 Pro 5G', 'iQOO Neo 8 5G',
                // Z Series Budget
                'iQOO Z9 Turbo 5G', 'iQOO Z9 5G', 'iQOO Z9s',
                'iQOO Z8 Pro 5G', 'iQOO Z8 5G',
                'iQOO Z7 Pro 5G', 'iQOO Z7 5G',
            ],
            'Poco' => [
                // F Series Flagship
                'Poco F6 Pro 5G', 'Poco F6 5G',
                'Poco F5 Pro 5G', 'Poco F5 5G',
                'Poco F4 5G', 'Poco F3 5G', 'Poco F3',
                // X Series
                'Poco X7 Pro 5G', 'Poco X7 5G', 'Poco X6 Pro 5G',
                'Poco X5 Pro 5G', 'Poco X5 5G', 'Poco X5',
                'Poco X4 Pro 5G', 'Poco X4 5G', 'Poco X4',
                // M Series Budget
                'Poco M7 Pro 5G', 'Poco M6 Pro 5G',
                'Poco M6 5G', 'Poco M5 5G', 'Poco M5',
                'Poco M4 Pro 5G', 'Poco M4 Pro', 'Poco M4',
                // C Series Entry
                'Poco C75 5G', 'Poco C65', 'Poco C55',
            ],
            'Nothing' => [
                // Latest Series
                'Nothing Phone (3a) Pro', 'Nothing Phone (3a)',
                'Nothing Phone (3)', 'Nothing Phone (2a) Plus',
                'Nothing Phone (2a)', 'Nothing Phone (2)',
                'Nothing Phone (1)',
            ],
            'Lava' => [
                // Flagship Series
                'Lava Agni 3 5G', 'Lava Agni 2 5G', 'Lava Agni 2',
                // Mid-Range
                'Lava Blaze 3 5G', 'Lava Blaze 2 5G', 'Lava Blaze 2 Pro',
                'Lava Blaze', 'Lava Blaze Curve',
                // Budget
                'Lava Yuva 3 Pro', 'Lava Yuva 3', 'Lava Yuva 2',
                'Lava Yuva Star',
            ],
            'Infinix' => [
                // GT Series Gaming
                'Infinix GT 20 Pro', 'Infinix GT 20', 'Infinix GT 10 Pro',
                // Zero Series Flagship
                'Infinix Zero 40 5G', 'Infinix Zero 40', 'Infinix Zero 30',
                // Note Series
                'Infinix Note 40 Pro+ 5G', 'Infinix Note 40 Pro 5G', 'Infinix Note 40',
                'Infinix Note 30 Pro 5G', 'Infinix Note 30 5G', 'Infinix Note 30',
                // Hot Series
                'Infinix Hot 50 Pro+ 5G', 'Infinix Hot 50 5G',
                'Infinix Hot 40 Pro 5G', 'Infinix Hot 40 Pro',
                // Smart Series Budget
                'Infinix Smart 8 Plus', 'Infinix Smart 8', 'Infinix Smart 7',
            ],
            'Tecno' => [
                // Spark Series
                'Tecno Spark 30 Pro 5G', 'Tecno Spark 30 5G', 'Tecno Spark 30',
                'Tecno Spark 20 Pro 5G', 'Tecno Spark 20', 'Tecno Spark 10 Pro',
                // Camon Series Photo Phones
                'Tecno Camon 30 Pro 5G', 'Tecno Camon 30 5G', 'Tecno Camon 30',
                'Tecno Camon 20 Pro 5G', 'Tecno Camon 20', 'Tecno Camon 19',
                // Pova Series
                'Tecno Pova 6 Pro 5G', 'Tecno Pova 6 5G', 'Tecno Pova 6',
                'Tecno Pova 5 Pro 5G', 'Tecno Pova 5 5G',
                // Phantom Series Foldable
                'Tecno Phantom V Fold 5G', 'Tecno Phantom V Flip 5G',
                'Tecno Phantom V 5G',
            ],
            'HMD' => [
                // Latest & Budget
                'HMD Skyline', 'HMD Pulse Pro', 'HMD Pulse', 'HMD Pulse Plus',
                'HMD Fusion', 'HMD Icon', 'HMD Vibe', 'HMD Vibe Plus',
                'HMD 5G', 'HMD 4G',
            ],
            'Asus' => [
                // ROG Gaming Phone
                'ROG Phone 8 Pro', 'ROG Phone 8', 'ROG Phone 7 Ultimate',
                'ROG Phone 7 Pro', 'ROG Phone 7',
                // ZenFone Series
                'ZenFone 11 Ultra', 'ZenFone 11', 'ZenFone 10', 'ZenFone 9',
                'ZenFone 8 Flip', 'ZenFone 8', 'ZenFone 8 Pro',
            ],
            'Sony' => [
                // Xperia Latest
                'Xperia 1 VI', 'Xperia 5 V', 'Xperia 10 VI',
                'Xperia 1 V', 'Xperia 5 IV', 'Xperia 10 V',
                'Xperia 1 IV', 'Xperia 5 III', 'Xperia 10 IV',
                // Older Xperia
                'Xperia 1 III', 'Xperia 5 II', 'Xperia XZ3',
                'Xperia XZ2', 'Xperia XZ1', 'Xperia XZ',
            ],
            'Lenovo' => [
                // Legion Gaming
                'Lenovo Legion Y90', 'Lenovo Legion Y700', 'Lenovo Legion Y70',
                // ThinkPhone
                'Lenovo ThinkPhone by Motorola',
                // Budget
                'Lenovo K13 Note', 'Lenovo K12', 'Lenovo A12', 'Lenovo A10',
                'Lenovo Tab P12 Pro',
            ],
            'Micromax' => [
                // IN Series
                'Micromax IN 2C', 'Micromax IN 1', 'Micromax IN 1b',
                'Micromax IN Note 2', 'Micromax IN Note 1', 'Micromax IN Note 1 Pro',
                // Canvas Series
                'Micromax Canvas 5G', 'Micromax Canvas Air',
            ],
            'Huawei' => [
                // Mate Series
                'Huawei Mate 70 Pro+', 'Huawei Mate 70 Pro', 'Huawei Mate 70',
                'Huawei Mate 60 Pro+', 'Huawei Mate 60 Pro', 'Huawei Mate 60',
                'Huawei Mate 50 Pro', 'Huawei Mate 50',
                // P Series
                'Huawei P70 Pro+', 'Huawei P70 Pro', 'Huawei P70',
                'Huawei P60 Pro', 'Huawei P60', 'Huawei P60 Art',
                // Nova Series
                'Huawei Nova 13 Pro', 'Huawei Nova 13', 'Huawei Nova 12 Pro',
                'Huawei Nova Y90', 'Huawei Nova Y70 Pro',
            ],
            'ZTE' => [
                // Nubia Series
                'ZTE Nubia Z70 Ultra', 'ZTE Nubia Z60 Ultra',
                'ZTE Nubia Flip 5G', 'ZTE Nubia Z Fold',
                // Budget
                'ZTE Blade A72', 'ZTE Blade A53',
            ],
            'Honor' => [
                // Magic Series
                'Honor Magic 7 Pro', 'Honor Magic 7', 'Honor Magic V3',
                'Honor Magic 6 Pro', 'Honor Magic 6', 'Honor Magic V2',
                // 90 & 100 Series
                'Honor 90 Pro', 'Honor 90', 'Honor 100 Pro', 'Honor 100',
                // X Series
                'Honor X60 Pro', 'Honor X60', 'Honor X50 5G',
                // Play Series
                'Honor Play 50 Pro', 'Honor Play 40', 'Honor Play 30',
            ],
            'Fairphone' => [
                // Repairable Phones
                'Fairphone 5', 'Fairphone 4', 'Fairphone 3+', 'Fairphone 3',
            ],
            'Palm' => [
                // Original Palm Phones
                'Palm Phone (2018)',
                'Palm Pixi Plus', 'Palm Pre Plus', 'Palm Pixi',
            ],
            'Wise' => [
                // Budget Brand
                'Wise Tiger 8', 'Wise Tiger 7',
            ],
            'Transsion' => [
                // African Market Brands
                'Transsion Tecno Spark 30',
                'Transsion Itel A70',
            ],
            'Dooee' => [
                // Budget Phones
                'Dooee K1', 'Dooee K2', 'Dooee K3',
            ],
            'Ulefone' => [
                // Rugged Phones
                'Ulefone Armor 15', 'Ulefone Armor 14',
                'Ulefone Power Armor 18', 'Ulefone Power Armor 17',
                'Ulefone Note 20 Pro', 'Ulefone Note 15 Pro',
            ],
            'Cubot' => [
                // Budget/Rugged
                'Cubot King Kong 9', 'Cubot King Kong 8', 'Cubot Kingkong Ace',
                'Cubot X90', 'Cubot Max 3', 'Cubot Note 21',
            ],
            'Blackview' => [
                // Rugged Phones
                'Blackview BV9300', 'Blackview BV8300', 'Blackview BV9200',
                'Blackview A96', 'Blackview A95', 'Blackview A100',
                'Blackview Tab 20', 'Blackview Tab 10',
            ],
            'Other' => [
                'Other / Custom', 'Generic Phone', 'Unknown Model',
            ],
        ];
    }
}
