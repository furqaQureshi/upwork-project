<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ElectronicsAccessorySeeder extends Seeder
{
    public function run(): void
    {
        $electronics = Category::where('name', 'Electronics')->first();

        if (! $electronics) {
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
        }

        $catalog = [
            [
                'name' => 'Chargers',
                'models_by_brand' => $this->chargerModelsByBrand(),
                'variants_by_model' => $this->chargerVariantsByModel(),
            ],
            [
                'name' => 'Cables',
                'models_by_brand' => $this->cableModelsByBrand(),
                'variants_by_model' => $this->cableVariantsByModel(),
            ],
            [
                'name' => 'Routers',
                'models_by_brand' => $this->routerModelsByBrand(),
                'variants_by_model' => $this->routerVariantsByModel(),
            ],
            [
                'name' => 'Monitors',
                'models_by_brand' => $this->monitorModelsByBrand(),
                'variants_by_model' => $this->monitorVariantsByModel(),
            ],
            [
                'name' => 'Printers',
                'models_by_brand' => $this->printerModelsByBrand(),
                'variants_by_model' => $this->printerVariantsByModel(),
            ],
            [
                'name' => 'Components',
                'models_by_brand' => $this->componentModelsByBrand(),
                'variants_by_model' => $this->componentVariantsByModel(),
            ],
        ];

        foreach ($catalog as $index => $item) {
            $existing = Category::where('name', $item['name'])->first();

            $subcategory = Category::updateOrCreate(
                ['name' => $item['name']],
                [
                    'parent_id' => $electronics->id,
                    'slug' => $this->uniqueCategorySlug($item['name'], $existing?->id),
                    'icon' => 'bolt',
                    'sort_order' => 300 + $index,
                    'is_active' => true,
                    'condition_enabled' => true,
                ]
            );

            $this->seedDependentFields(
                categoryId: (int) $subcategory->id,
                modelsByBrand: $item['models_by_brand'],
                variantsByModel: $item['variants_by_model']
            );

            $this->command->info('Electronics accessory seeded: '.$subcategory->name);
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

    private function chargerModelsByBrand(): array
    {
        return [
            'Anker' => ['Nano 3 30W', 'Nano II 65W', 'PowerPort III 20W'],
            'Belkin' => ['BoostCharge 20W', 'BoostCharge Pro 65W', 'Dual USB-C 40W'],
            'Apple' => ['20W USB-C Adapter', '35W Dual USB-C Adapter', '70W USB-C Adapter', '96W USB-C Adapter'],
            'Samsung' => ['25W Super Fast Charger', '45W Super Fast Charger', '65W Trio Adapter'],
            'OnePlus' => ['SUPERVOOC 80W', 'SUPERVOOC 100W', 'Warp Charge 65'],
            'Xiaomi' => ['33W SonicCharge', '67W Turbo Charger', '120W HyperCharge'],
            'realme' => ['33W Dart Charger', '67W SUPERVOOC Charger'],
            'boAt' => ['Dual Port 38W', 'GaN 65W Charger'],
            'UGREEN' => ['Nexode 45W', 'Nexode 65W', 'Nexode 100W'],
            'Portronics' => ['Adapto 20', 'Adapto 65', 'Adapto 100'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function chargerVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->chargerModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = ['Indian Plug', 'With Cable', 'Adapter Only'];
            }
        }

        $variants['Other / Custom'] = ['USB-A', 'USB-C', 'GaN'];

        return $variants;
    }

    private function cableModelsByBrand(): array
    {
        return [
            'Apple' => ['USB-C to Lightning Cable', 'USB-C Charge Cable', 'MagSafe 3 Cable'],
            'Samsung' => ['Type-C to Type-C Cable', 'USB-A to Type-C Cable'],
            'OnePlus' => ['Red Cable Type-C', 'SUPERVOOC Type-C Cable'],
            'Xiaomi' => ['Mi Type-C Cable', 'Mi USB-A to Type-C Cable'],
            'Anker' => ['PowerLine III USB-C', 'PowerLine Select+ USB-C'],
            'Belkin' => ['BoostCharge USB-C Cable', 'BoostCharge Lightning Cable'],
            'boAt' => ['Deuce USB-C Cable', 'Deuce Lightning Cable'],
            'Amazon Basics' => ['USB-C 2.0 Cable', 'USB-C to Lightning Cable'],
            'UGREEN' => ['USB-C 100W Cable', 'USB-C to Lightning MFi Cable'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function cableVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->cableModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = ['1 Meter', '1.5 Meter', '2 Meter', 'Braided'];
            }
        }

        $variants['Other / Custom'] = ['Type-C', 'Lightning', 'Micro USB'];

        return $variants;
    }

    private function routerModelsByBrand(): array
    {
        return [
            'TP-Link' => ['Archer C6', 'Archer AX10', 'Archer AX55'],
            'D-Link' => ['DIR-615', 'DIR-825', 'DIR-X1560'],
            'Netgear' => ['R6120', 'R6850', 'Nighthawk AX4'],
            'ASUS' => ['RT-AC59U', 'RT-AX55', 'RT-AX82U'],
            'Tenda' => ['N301', 'AC10', 'RX3'],
            'Xiaomi' => ['Mi Router 4A', 'Mi AX1800'],
            'Airtel' => ['Xstream Fiber Router', 'Airtel Wi-Fi 6 Router'],
            'Jio' => ['JioFiber Router', 'Jio AirFiber Router'],
            'Mercusys' => ['MW325R', 'AC12G', 'MR70X'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function routerVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->routerModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = ['Single Band', 'Dual Band', 'Wi-Fi 6'];
            }
        }

        $variants['Other / Custom'] = ['N300', 'AC1200', 'AX3000'];

        return $variants;
    }

    private function monitorModelsByBrand(): array
    {
        return [
            'Dell' => ['E2222H', 'S2421HN', 'P2723D', 'G2724D'],
            'LG' => ['22MP400', '24MR400', '27QN600', 'UltraGear 27GR75Q'],
            'Samsung' => ['LF24T350', 'M5 Smart Monitor', 'Odyssey G5'],
            'Acer' => ['EK220Q', 'SA242Y', 'Nitro VG240Y'],
            'ASUS' => ['VA24EHF', 'ProArt PA278QV', 'TUF VG27AQ'],
            'BenQ' => ['GW2480', 'EW3270U', 'MOBIUZ EX2710Q'],
            'MSI' => ['PRO MP223', 'MAG 274QRF'],
            'ViewSonic' => ['VA2432-H', 'VX2728J'],
            'HP' => ['M22f', 'X24ih', 'Omen 27q'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function monitorVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->monitorModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = ['22 inch FHD', '24 inch FHD', '27 inch QHD'];
            }
        }

        $variants['Other / Custom'] = ['FHD 60Hz', 'FHD 144Hz', 'QHD 165Hz'];

        return $variants;
    }

    private function printerModelsByBrand(): array
    {
        return [
            'HP' => ['DeskJet 2331', 'Ink Tank 419', 'Laser 108w', 'Laser MFP 136w'],
            'Canon' => ['PIXMA E470', 'PIXMA G3010', 'imageCLASS LBP6030w'],
            'Epson' => ['EcoTank L3211', 'EcoTank L3252', 'EcoTank L6270'],
            'Brother' => ['HL-B2000D', 'DCP-B7500D', 'DCP-T426W'],
            'Pantum' => ['P2509W', 'M6509NW'],
            'Ricoh' => ['SP 111', 'IM C300'],
            'Xerox' => ['B225', 'B305'],
            'Samsung' => ['Xpress M2021', 'Xpress M2071'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function printerVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->printerModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = ['Single Function', 'Multi Function', 'Wi-Fi'];
            }
        }

        $variants['Other / Custom'] = ['Inkjet', 'Laser', 'All-in-One'];

        return $variants;
    }

    private function componentModelsByBrand(): array
    {
        return [
            'Intel' => ['Core i3-12100F', 'Core i5-13400F', 'Core i7-14700K'],
            'AMD' => ['Ryzen 5 5600', 'Ryzen 7 7700', 'Ryzen 9 7900X'],
            'NVIDIA' => ['RTX 3050', 'RTX 4060', 'RTX 4070 Super'],
            'ASUS' => ['TUF B760M-Plus', 'Prime B650M-A', 'ROG Strix X670E-E'],
            'MSI' => ['PRO B760M-A', 'MAG B650 Tomahawk', 'Gaming X Trio RTX 4070'],
            'Gigabyte' => ['B760M DS3H', 'B650 AORUS Elite', 'RTX 4060 Windforce'],
            'Corsair' => ['Vengeance 16GB DDR5', 'RM750e PSU', 'MP600 SSD'],
            'Kingston' => ['FURY Beast 16GB DDR5', 'NV2 NVMe SSD'],
            'WD' => ['Blue SN580', 'Black SN850X'],
            'Seagate' => ['Barracuda 1TB', 'Barracuda 2TB'],
            'Cooler Master' => ['MWE 650 Bronze', 'Hyper 212', 'MasterBox TD500'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function componentVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->componentModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultComponentVariants($model);
            }
        }

        $variants['Other / Custom'] = ['Entry', 'Mid', 'High End'];

        return $variants;
    }

    private function defaultComponentVariants(string $model): array
    {
        if (preg_match('/RTX|Windforce|Gaming X/i', $model)) {
            return ['8GB', '12GB', '16GB'];
        }

        if (preg_match('/DDR5|FURY|Vengeance/i', $model)) {
            return ['16GB', '32GB', '64GB'];
        }

        if (preg_match('/SSD|SN580|SN850|NV2|MP600/i', $model)) {
            return ['500GB', '1TB', '2TB'];
        }

        if (preg_match('/PSU|Bronze|RM750/i', $model)) {
            return ['550W', '650W', '750W'];
        }

        if (preg_match('/Core|Ryzen/i', $model)) {
            return ['Base', 'Unlocked', 'Box with Cooler'];
        }

        return ['Base', 'Mid', 'Top'];
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
