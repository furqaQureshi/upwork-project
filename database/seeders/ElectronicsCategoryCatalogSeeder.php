<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ElectronicsCategoryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $electronics = Category::updateOrCreate(
            ['name' => 'Electronics'],
            [
                'parent_id' => null,
                'slug' => $this->uniqueCategorySlug('Electronics'),
                'icon' => 'bolt',
                'sort_order' => 4,
                'is_active' => true,
                'condition_enabled' => true,
            ]
        );

        $catalog = $this->electronicsCatalog();

        foreach ($catalog as $index => $item) {
            $existing = Category::where('name', $item['name'])->first();

            $subcategory = Category::updateOrCreate(
                ['name' => $item['name']],
                [
                    'parent_id' => $electronics->id,
                    'slug' => $this->uniqueCategorySlug($item['name'], $existing?->id),
                    'icon' => 'bolt',
                    'sort_order' => 100 + $index,
                    'is_active' => true,
                    'condition_enabled' => true,
                ]
            );

            $this->seedDependentFields(
                categoryId: (int) $subcategory->id,
                modelsByBrand: $item['models_by_brand'],
                variantsByModel: $item['variants_by_model']
            );

            $this->command->info("Electronics subcategory seeded: {$subcategory->name}");
        }
    }

    private function seedDependentFields(int $categoryId, array $modelsByBrand, array $variantsByModel): void
    {
        CustomField::where('category_id', $categoryId)
            ->whereIn('slug', ['brand', 'model', 'variant'])
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
            'options' => array_keys($modelsByBrand),
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
            'options' => $modelsByBrand,
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
            'options' => $variantsByModel,
            'sort_order' => 30,
            'is_required' => false,
            'is_active' => true,
        ]);
    }

    private function electronicsCatalog(): array
    {
        return [
            [
                'name' => 'Laptops',
                'models_by_brand' => $this->laptopModelsByBrand(),
                'variants_by_model' => $this->laptopVariantsByModel(),
            ],
            [
                'name' => 'Televisions',
                'models_by_brand' => $this->televisionModelsByBrand(),
                'variants_by_model' => $this->televisionVariantsByModel(),
            ],
            [
                'name' => 'Cameras',
                'models_by_brand' => $this->cameraModelsByBrand(),
                'variants_by_model' => $this->cameraVariantsByModel(),
            ],
            [
                'name' => 'Tablets',
                'models_by_brand' => $this->tabletModelsByBrand(),
                'variants_by_model' => $this->tabletVariantsByModel(),
            ],
            [
                'name' => 'Smartwatches',
                'models_by_brand' => $this->smartwatchModelsByBrand(),
                'variants_by_model' => $this->smartwatchVariantsByModel(),
            ],
            [
                'name' => 'Headphones and Earphones',
                'models_by_brand' => $this->audioModelsByBrand(),
                'variants_by_model' => $this->audioVariantsByModel(),
            ],
            [
                'name' => 'Gaming Consoles',
                'models_by_brand' => $this->consoleModelsByBrand(),
                'variants_by_model' => $this->consoleVariantsByModel(),
            ],
            [
                'name' => 'Air Conditioners',
                'models_by_brand' => $this->acModelsByBrand(),
                'variants_by_model' => $this->acVariantsByModel(),
            ],
            [
                'name' => 'Refrigerators',
                'models_by_brand' => $this->refrigeratorModelsByBrand(),
                'variants_by_model' => $this->refrigeratorVariantsByModel(),
            ],
            [
                'name' => 'Washing Machines',
                'models_by_brand' => $this->washingMachineModelsByBrand(),
                'variants_by_model' => $this->washingMachineVariantsByModel(),
            ],
        ];
    }

    private function laptopModelsByBrand(): array
    {
        return [
            'Apple' => ['MacBook Air M2', 'MacBook Air M3', 'MacBook Pro 14', 'MacBook Pro 16'],
            'Dell' => ['Inspiron 15', 'Vostro 3520', 'G15', 'XPS 13', 'Alienware m16'],
            'HP' => ['15s', 'Pavilion 14', 'Envy x360', 'Victus 15', 'Omen 16'],
            'Lenovo' => ['IdeaPad Slim 3', 'IdeaPad Gaming 3', 'ThinkPad E14', 'Yoga Slim 7', 'Legion 5'],
            'Asus' => ['Vivobook 15', 'Zenbook 14', 'TUF Gaming F15', 'ROG Strix G16'],
            'Acer' => ['Aspire 7', 'Aspire Lite', 'Nitro V 15', 'Predator Helios Neo 16'],
            'MSI' => ['Thin GF63', 'Cyborg 15', 'Katana 15'],
            'Samsung' => ['Galaxy Book4', 'Galaxy Book4 Pro'],
            'Microsoft' => ['Surface Laptop 5', 'Surface Pro 9'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function laptopVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->laptopModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultLaptopVariants($model);
            }
        }

        $overrides = [
            'MacBook Air M2' => ['8GB/256GB', '8GB/512GB', '16GB/512GB', '24GB/1TB'],
            'MacBook Air M3' => ['8GB/256GB', '8GB/512GB', '16GB/512GB', '24GB/1TB'],
            'MacBook Pro 14' => ['M3 8GB/512GB', 'M3 Pro 18GB/512GB', 'M3 Max 36GB/1TB'],
            'MacBook Pro 16' => ['M3 Pro 18GB/512GB', 'M3 Max 36GB/1TB', 'M3 Max 48GB/1TB'],
            'Surface Pro 9' => ['i5 8GB/256GB', 'i5 16GB/256GB', 'i7 16GB/512GB'],
            'Surface Laptop 5' => ['i5 8GB/512GB', 'i7 16GB/512GB', 'i7 16GB/1TB'],
            'Other / Custom' => ['Base', 'Mid', 'Top'],
        ];

        foreach ($overrides as $model => $modelVariants) {
            $variants[$model] = $modelVariants;
        }

        return $variants;
    }

    private function defaultLaptopVariants(string $model): array
    {
        if (preg_match('/Gaming|ROG|TUF|Alienware|Legion|Omen|Predator|Nitro|G15/i', $model)) {
            return ['i5 + RTX 3050', 'i7 + RTX 4060', 'i9 + RTX 4070'];
        }

        return ['i3 8GB/512GB', 'i5 16GB/512GB', 'i7 16GB/1TB'];
    }

    private function televisionModelsByBrand(): array
    {
        return [
            'Samsung' => ['Crystal 4K', 'Q60D QLED', 'QN85D Neo QLED', 'The Frame'],
            'LG' => ['UR7500 4K', 'QNED80', 'C3 OLED', 'G4 OLED'],
            'Sony' => ['Bravia X74L', 'Bravia X82L', 'Bravia X90L', 'Bravia A80L OLED'],
            'TCL' => ['P635 4K', 'C645 QLED', 'C755 Mini LED'],
            'Mi' => ['X Series 4K', 'A Series', 'Q1 QLED'],
            'OnePlus' => ['Y1S Pro', 'Y Series 4K', 'Q2 Pro'],
            'Panasonic' => ['MX740 4K', 'LX650', 'MZ980 OLED'],
            'Hisense' => ['A6K 4K', 'E7K QLED', 'U7K Mini LED'],
            'Vu' => ['GloLED', 'Masterpiece Glo', 'Cinema TV'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function televisionVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->televisionModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultTelevisionVariants($model);
            }
        }

        $variants['Other / Custom'] = ['32 inch', '43 inch', '55 inch', '65 inch'];

        return $variants;
    }

    private function defaultTelevisionVariants(string $model): array
    {
        if (preg_match('/OLED/i', $model)) {
            return ['55 inch', '65 inch', '77 inch'];
        }

        if (preg_match('/QLED|Mini|Neo/i', $model)) {
            return ['43 inch', '55 inch', '65 inch', '75 inch'];
        }

        return ['32 inch', '43 inch', '50 inch', '55 inch'];
    }

    private function cameraModelsByBrand(): array
    {
        return [
            'Canon' => ['EOS 1500D', 'EOS 200D II', 'EOS R50', 'EOS R10', 'EOS R8'],
            'Nikon' => ['D7500', 'Z30', 'Z50', 'Z fc', 'Z6 II'],
            'Sony' => ['Alpha A6100', 'Alpha A6400', 'Alpha A6700', 'Alpha ZV-E10', 'Alpha A7 IV'],
            'Fujifilm' => ['X-T30 II', 'X-S20', 'X-T5', 'X100VI'],
            'Panasonic' => ['Lumix G85', 'Lumix G100', 'Lumix S5 II'],
            'GoPro' => ['HERO12 Black', 'HERO11 Black'],
            'DJI' => ['Osmo Action 4', 'Pocket 3'],
            'Leica' => ['D-Lux 8', 'Q3'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function cameraVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->cameraModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultCameraVariants($model);
            }
        }

        $overrides = [
            'X100VI' => ['Standard', 'Limited Edition'],
            'Q3' => ['Standard', '190th Anniversary Edition'],
            'Other / Custom' => ['Body Only', 'Kit Lens', 'Bundle'],
        ];

        foreach ($overrides as $model => $modelVariants) {
            $variants[$model] = $modelVariants;
        }

        return $variants;
    }

    private function defaultCameraVariants(string $model): array
    {
        if (preg_match('/HERO|Action|Pocket/i', $model)) {
            return ['Standard', 'Creator Kit', 'Adventure Bundle'];
        }

        if (preg_match('/A7|Z6|S5|R8/i', $model)) {
            return ['Body Only', '24-70mm Kit', 'Dual Lens Kit'];
        }

        return ['Body Only', '18-55mm Kit', '18-135mm Kit'];
    }

    private function tabletModelsByBrand(): array
    {
        return [
            'Apple' => ['iPad 10th Gen', 'iPad Air M2', 'iPad Pro 11 M4', 'iPad Pro 13 M4', 'iPad mini 6'],
            'Samsung' => ['Galaxy Tab A9', 'Galaxy Tab A9+', 'Galaxy Tab S9 FE', 'Galaxy Tab S9 FE+', 'Galaxy Tab S9', 'Galaxy Tab S9 Ultra'],
            'Lenovo' => ['Tab M10', 'Tab P11', 'Tab P12', 'Legion Tab'],
            'Xiaomi' => ['Redmi Pad SE', 'Redmi Pad Pro', 'Xiaomi Pad 6'],
            'OnePlus' => ['OnePlus Pad', 'OnePlus Pad Go'],
            'Realme' => ['Realme Pad 2', 'Realme Pad X'],
            'Amazon' => ['Fire HD 8', 'Fire HD 10'],
            'Huawei' => ['MatePad 11.5', 'MatePad Pro 13.2'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function tabletVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->tabletModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultTabletVariants($model);
            }
        }

        $overrides = [
            'iPad Pro 11 M4' => ['Wi-Fi 256GB', 'Wi-Fi 512GB', 'Wi-Fi + Cellular 512GB', 'Wi-Fi + Cellular 1TB'],
            'iPad Pro 13 M4' => ['Wi-Fi 256GB', 'Wi-Fi 512GB', 'Wi-Fi + Cellular 512GB', 'Wi-Fi + Cellular 1TB'],
            'Fire HD 8' => ['32GB', '64GB'],
            'Fire HD 10' => ['32GB', '64GB'],
            'Other / Custom' => ['Wi-Fi', 'Wi-Fi + Cellular', 'Premium'],
        ];

        foreach ($overrides as $model => $modelVariants) {
            $variants[$model] = $modelVariants;
        }

        return $variants;
    }

    private function defaultTabletVariants(string $model): array
    {
        return ['Wi-Fi 128GB', 'Wi-Fi 256GB', '5G 128GB', '5G 256GB'];
    }

    private function smartwatchModelsByBrand(): array
    {
        return [
            'Apple' => ['Watch SE 2', 'Watch Series 9', 'Watch Ultra 2'],
            'Samsung' => ['Galaxy Watch4', 'Galaxy Watch5', 'Galaxy Watch6', 'Galaxy Watch7', 'Galaxy Watch Ultra'],
            'Noise' => ['ColorFit Pro 5', 'ColorFit Ultra 3'],
            'boAt' => ['Wave Sigma 3', 'Lunar Connect Pro'],
            'Amazfit' => ['Bip 5', 'GTR 4', 'Cheetah'],
            'Fitbit' => ['Charge 6', 'Versa 4', 'Sense 2'],
            'Garmin' => ['Forerunner 165', 'Venu 3', 'Instinct 2'],
            'Huawei' => ['Watch GT 4', 'Watch Fit 3'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function smartwatchVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->smartwatchModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultSmartwatchVariants($model);
            }
        }

        $overrides = [
            'Watch Ultra 2' => ['49mm GPS + Cellular', 'Ocean Band', 'Trail Loop'],
            'Watch Series 9' => ['41mm GPS', '45mm GPS', '45mm GPS + Cellular'],
            'Watch SE 2' => ['40mm GPS', '44mm GPS', '44mm GPS + Cellular'],
            'Galaxy Watch Ultra' => ['47mm Bluetooth', '47mm LTE'],
            'Other / Custom' => ['Bluetooth', 'LTE', 'Premium'],
        ];

        foreach ($overrides as $model => $modelVariants) {
            $variants[$model] = $modelVariants;
        }

        return $variants;
    }

    private function defaultSmartwatchVariants(string $model): array
    {
        if (preg_match('/Watch|Galaxy|Venu|Instinct|Fit/i', $model)) {
            return ['Bluetooth', 'Bluetooth + GPS', 'LTE'];
        }

        return ['Standard', 'AMOLED', 'NFC'];
    }

    private function audioModelsByBrand(): array
    {
        return [
            'Sony' => ['WH-CH520', 'WH-1000XM5', 'WF-1000XM5', 'INZONE H5'],
            'boAt' => ['Airdopes 141', 'Airdopes 170', 'Rockerz 450', 'Nirvana 751 ANC'],
            'JBL' => ['Tune 770NC', 'Live 660NC', 'Wave Beam', 'C100SI'],
            'Realme' => ['Buds T300', 'Buds Air 6 Pro', 'Buds Wireless 3'],
            'OnePlus' => ['Nord Buds 2r', 'Nord Buds 3 Pro', 'Buds Pro 3'],
            'Apple' => ['AirPods 2nd Gen', 'AirPods 3rd Gen', 'AirPods Pro 2', 'AirPods Max'],
            'Sennheiser' => ['HD 450BT', 'Accentum', 'Momentum 4', 'CX True Wireless'],
            'Bose' => ['QuietComfort Headphones', 'QuietComfort Ultra', 'Sport Earbuds'],
            'Nothing' => ['Ear (a)', 'Ear (2)', 'CMF Buds Pro 2'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function audioVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->audioModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultAudioVariants($model);
            }
        }

        $overrides = [
            'C100SI' => ['Wired 3.5mm', 'Wired Type-C'],
            'AirPods Max' => ['Standard', 'USB-C Version'],
            'Other / Custom' => ['Wired', 'Wireless', 'ANC'],
        ];

        foreach ($overrides as $model => $modelVariants) {
            $variants[$model] = $modelVariants;
        }

        return $variants;
    }

    private function defaultAudioVariants(string $model): array
    {
        if (preg_match('/AirPods|Buds|Earbuds|WF-|Wave|CX/i', $model)) {
            return ['Standard', 'ANC', 'Pro'];
        }

        if (preg_match('/Headphones|WH-|Rockerz|Momentum|QuietComfort|INZONE/i', $model)) {
            return ['Bluetooth', 'ANC', 'Premium'];
        }

        return ['Wired', 'Wireless', 'Top'];
    }

    private function consoleModelsByBrand(): array
    {
        return [
            'Sony' => ['PlayStation 5 Slim Disc', 'PlayStation 5 Slim Digital', 'PlayStation 4 Slim', 'PlayStation 4 Pro'],
            'Microsoft' => ['Xbox Series S', 'Xbox Series X', 'Xbox One S'],
            'Nintendo' => ['Switch', 'Switch OLED', 'Switch Lite'],
            'Valve' => ['Steam Deck LCD', 'Steam Deck OLED'],
            'Asus' => ['ROG Ally', 'ROG Ally X'],
            'Lenovo' => ['Legion Go'],
            'MSI' => ['Claw A1M'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function consoleVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->consoleModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultConsoleVariants($model);
            }
        }

        $overrides = [
            'PlayStation 5 Slim Disc' => ['Standard', 'Bundle + Controller', '1TB'],
            'PlayStation 5 Slim Digital' => ['Standard', 'Bundle + Controller', '1TB'],
            'Xbox Series S' => ['512GB', '1TB', 'Bundle'],
            'Xbox Series X' => ['1TB', '2TB', 'Bundle'],
            'Switch' => ['32GB', 'Bundle'],
            'Switch OLED' => ['64GB', 'Bundle'],
            'Switch Lite' => ['32GB', 'Pokemon Edition'],
            'ROG Ally' => ['Z1', 'Z1 Extreme'],
            'ROG Ally X' => ['24GB RAM / 1TB'],
            'Legion Go' => ['512GB', '1TB'],
            'Claw A1M' => ['Core Ultra 5', 'Core Ultra 7'],
            'Other / Custom' => ['Base', 'Bundle', 'Premium'],
        ];

        foreach ($overrides as $model => $modelVariants) {
            $variants[$model] = $modelVariants;
        }

        return $variants;
    }

    private function defaultConsoleVariants(string $model): array
    {
        return ['Base', 'Bundle', 'Top'];
    }

    private function acModelsByBrand(): array
    {
        return [
            'Daikin' => ['FTKF Inverter', 'JTKJ Inverter'],
            'LG' => ['Dual Inverter', 'Hot and Cold Dual Inverter'],
            'Voltas' => ['Adjustable Inverter', 'Vertis Elite Inverter'],
            'Blue Star' => ['Y Series Inverter', 'D Series Inverter'],
            'Samsung' => ['WindFree Inverter', 'Digital Inverter'],
            'Hitachi' => ['iZen Inverter', 'Kashikoi Inverter'],
            'Panasonic' => ['Miraie Inverter', 'Twin Cool Inverter'],
            'Lloyd' => ['GLS Inverter', 'Stylus Inverter'],
            'Carrier' => ['Emperia Inverter', 'Ester Pro Inverter'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function acVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->acModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = ['1 Ton 3 Star', '1.5 Ton 3 Star', '1.5 Ton 5 Star', '2 Ton 3 Star'];
            }
        }

        $variants['Other / Custom'] = ['Window AC', 'Split AC', 'Inverter AC'];

        return $variants;
    }

    private function refrigeratorModelsByBrand(): array
    {
        return [
            'LG' => ['GL-B201', 'GL-I292', 'GC-B257'],
            'Samsung' => ['RT28', 'RT34', 'RS76'],
            'Whirlpool' => ['Neo DF258', 'Intellifresh 3S', 'FP 313D'],
            'Haier' => ['HEF-272', 'HRF-355', 'HRS-682'],
            'Godrej' => ['RD EDGE 205', 'RF EON 260', 'Side by Side 564'],
            'Panasonic' => ['NR-BS60', 'NR-TG321', 'NR-TH272'],
            'Bosch' => ['KDN43', 'KDN56'],
            'Siemens' => ['iQ300 KG56'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function refrigeratorVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->refrigeratorModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = ['200L', '260L', '300L', '500L+'];
            }
        }

        $variants['Other / Custom'] = ['Single Door', 'Double Door', 'Side by Side'];

        return $variants;
    }

    private function washingMachineModelsByBrand(): array
    {
        return [
            'LG' => ['FHM1207', 'T70SKSF1Z', 'FHP1209Z5M'],
            'Samsung' => ['WW80T', 'WA70A', 'WW90T'],
            'Whirlpool' => ['Stainwash Pro', 'Magic Clean Pro', 'Bloomwash Pro'],
            'IFB' => ['Senator MXS', 'Executive Plus MXC'],
            'Bosch' => ['WAJ2426SIN', 'WGA252ZPIN'],
            'Haier' => ['HW70', 'HWM80', 'HW90'],
            'Godrej' => ['WTEON 700', 'WFEON 651'],
            'Panasonic' => ['NA-F70LF2', 'NA-127XB1'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function washingMachineVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->washingMachineModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = ['6.5 Kg', '7 Kg', '8 Kg', '9 Kg'];
            }
        }

        $variants['Other / Custom'] = ['Top Load', 'Front Load', 'Semi Automatic'];

        return $variants;
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
