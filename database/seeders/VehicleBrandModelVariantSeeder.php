<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CustomField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleBrandModelVariantSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategory(
            categoryNames: ['Cars', 'Car'],
            categorySlug: 'cars',
            modelsByBrand: $this->carModelsByBrand(),
            variantsByModel: $this->carVariantsByModel(),
            label: 'Cars',
            includeInsuranceStatus: true
        );

        $this->seedCategory(
            categoryNames: ['Bikes', 'Bike', 'Motorcycles'],
            categorySlug: 'bikes',
            modelsByBrand: $this->bikeModelsByBrand(),
            variantsByModel: $this->bikeVariantsByModel(),
            label: 'Bikes',
            includeInsuranceStatus: true
        );
    }

    private function seedCategory(array $categoryNames, string $categorySlug, array $modelsByBrand, array $variantsByModel, string $label, bool $includeInsuranceStatus = false): void
    {
        $category = null;

        foreach ($categoryNames as $name) {
            $category = Category::where('name', $name)->first();
            if ($category) {
                break;
            }
        }

        $category ??= Category::where('slug', $categorySlug)->first();

        if (! $category) {
            $this->command->warn("{$label} category not found. Skipping {$label} vehicle custom fields.");
            return;
        }

        $categoryId = (int) $category->id;

        CustomField::where('category_id', $categoryId)
            ->whereIn('slug', ['brand', 'model', 'variant', 'insurance-status'])
            ->get()
            ->each(function (CustomField $field): void {
                $field->delete();
            });

        $brandField = CustomField::create([
            'category_id' => $categoryId,
            'parent_field_id' => null,
            'name' => 'Brand',
            'slug' => $this->uniqueSlug('brand', $categoryId),
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
            'slug' => $this->uniqueSlug('model', $categoryId),
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
            'slug' => $this->uniqueSlug('variant', $categoryId),
            'field_type' => 'dropdown',
            'options' => $variantsByModel,
            'sort_order' => 30,
            'is_required' => false,
            'is_active' => true,
        ]);

        if ($includeInsuranceStatus) {
            CustomField::create([
                'category_id' => $categoryId,
                'parent_field_id' => null,
                'name' => 'Insurance Status',
                'slug' => $this->uniqueSlug('insurance-status', $categoryId),
                'field_type' => 'dropdown',
                'options' => ['Valid', 'Expired', 'Third Party', 'Comprehensive', 'No Insurance'],
                'sort_order' => 40,
                'is_required' => false,
                'is_active' => true,
            ]);
        }

        $this->command->info("{$label} Brand/Model/Variant fields created for '{$category->name}' (ID: {$categoryId}).");
    }

    private function carModelsByBrand(): array
    {
        return [
            'Maruti Suzuki' => ['Alto K10', 'S-Presso', 'WagonR', 'Celerio', 'Swift', 'Dzire', 'Baleno', 'Fronx', 'Brezza', 'Ertiga', 'XL6', 'Grand Vitara', 'Jimny', 'Invicto'],
            'Hyundai' => ['Grand i10 Nios', 'i20', 'Exter', 'Venue', 'Verna', 'Creta', 'Alcazar', 'Tucson', 'Ioniq 5'],
            'Tata' => ['Tiago', 'Tigor', 'Altroz', 'Punch', 'Nexon', 'Harrier', 'Safari', 'Curvv', 'Tiago EV', 'Tigor EV', 'Punch EV', 'Nexon EV'],
            'Mahindra' => ['Bolero', 'Bolero Neo', 'XUV 3XO', 'XUV400 EV', 'Scorpio N', 'Scorpio Classic', 'Thar', 'XUV700', 'BE 6'],
            'Toyota' => ['Glanza', 'Urban Cruiser Taisor', 'Urban Cruiser Hyryder', 'Innova Crysta', 'Innova Hycross', 'Fortuner', 'Camry', 'Hilux'],
            'Kia' => ['Sonet', 'Seltos', 'Carens', 'Syros', 'EV6'],
            'Honda' => ['Amaze', 'City', 'Elevate'],
            'Skoda' => ['Kylaq', 'Slavia', 'Kushaq', 'Superb', 'Kodiaq'],
            'Volkswagen' => ['Virtus', 'Taigun', 'Tiguan', 'ID.4'],
            'Renault' => ['Kwid', 'Kiger', 'Triber'],
            'Nissan' => ['Magnite', 'X-Trail'],
            'MG' => ['Comet EV', 'Astor', 'Hector', 'Hector Plus', 'Gloster', 'ZS EV', 'Windsor EV'],
            'Jeep' => ['Compass', 'Meridian', 'Wrangler', 'Grand Cherokee'],
            'BMW' => ['2 Series Gran Coupe', '3 Series LWB', '5 Series', 'X1', 'X3', 'X5', 'iX1', 'iX'],
            'Mercedes-Benz' => ['A-Class Limousine', 'C-Class', 'E-Class', 'GLA', 'GLC', 'GLS', 'EQA', 'EQS SUV'],
            'Audi' => ['A4', 'A6', 'Q3', 'Q5', 'Q7', 'Q8', 'e-tron', 'Q8 e-tron'],
            'Lexus' => ['ES', 'NX', 'RX', 'LX'],
            'Volvo' => ['XC40 Recharge', 'C40 Recharge', 'XC60', 'XC90'],
            'Jaguar' => ['F-Pace', 'I-Pace'],
            'Land Rover' => ['Defender', 'Discovery Sport', 'Range Rover Evoque', 'Range Rover Velar', 'Range Rover Sport'],
            'BYD' => ['Atto 3', 'Seal', 'eMAX 7'],
            'Citroen' => ['C3', 'Basalt', 'Aircross'],
            'Force Motors' => ['Gurkha'],
            'Isuzu' => ['D-Max V-Cross', 'MU-X'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function carVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->carModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultCarVariants($model);
            }
        }

        $overrides = [
            'Swift' => ['LXi', 'VXi', 'ZXi', 'ZXi Plus'],
            'Dzire' => ['LXi', 'VXi', 'ZXi', 'ZXi Plus'],
            'Baleno' => ['Sigma', 'Delta', 'Zeta', 'Alpha'],
            'Brezza' => ['LXi', 'VXi', 'ZXi', 'ZXi Plus'],
            'Creta' => ['E', 'EX', 'S', 'SX', 'SX(O)'],
            'Venue' => ['E', 'S', 'S(O)', 'SX', 'SX(O)'],
            'Nexon' => ['Smart', 'Pure', 'Creative', 'Fearless'],
            'Punch' => ['Pure', 'Adventure', 'Accomplished', 'Creative'],
            'Harrier' => ['Smart', 'Pure', 'Adventure', 'Fearless'],
            'Safari' => ['Smart', 'Pure', 'Adventure', 'Accomplished'],
            'XUV700' => ['MX', 'AX3', 'AX5', 'AX7', 'AX7L'],
            'Thar' => ['AX (O)', 'LX Hard Top', 'LX Soft Top'],
            'Scorpio N' => ['Z2', 'Z4', 'Z6', 'Z8', 'Z8L'],
            'Fortuner' => ['4x2 MT', '4x2 AT', '4x4 MT', '4x4 AT', 'Legender'],
            'Sonet' => ['HTE', 'HTK', 'HTK+', 'HTX', 'GTX+'],
            'Seltos' => ['HTE', 'HTK', 'HTK+', 'HTX', 'GTX+'],
            'City' => ['SV', 'V', 'VX', 'ZX'],
            'Amaze' => ['E', 'S', 'VX', 'ZX'],
            'Virtus' => ['Comfortline', 'Highline', 'Topline', 'GT Plus'],
            'Taigun' => ['Comfortline', 'Highline', 'Topline', 'GT Plus'],
            'Slavia' => ['Classic', 'Signature', 'Sportline', 'Prestige'],
            'Kushaq' => ['Classic', 'Signature', 'Sportline', 'Prestige'],
            'Kylaq' => ['Classic', 'Signature', 'Sportline', 'Prestige'],
            'Altroz' => ['XE', 'XM', 'XT', 'XZ', 'XZ Plus'],
            'Verna' => ['EX', 'S', 'SX', 'SX Turbo', 'SX(O)'],
            'Hector' => ['Style', 'Shine', 'Smart', 'Sharp', 'Savvy Pro'],
            'Compass' => ['Sport', 'Longitude', 'Limited (O)', 'Model S'],
            'Other / Custom' => ['Base', 'Mid', 'Top'],
        ];

        foreach ($overrides as $model => $modelVariants) {
            $variants[$model] = $modelVariants;
        }

        return $variants;
    }

    private function defaultCarVariants(string $model): array
    {
        if (preg_match('/EV|e-tron|Recharge|Ioniq|Comet|Atto|Seal|iX|EQA|EQS/i', $model)) {
            return ['Standard Range', 'Long Range', 'Top', 'Dual Motor'];
        }

        if (preg_match('/Fortuner|Hilux|Wrangler|Defender|Gurkha|MU-X/i', $model)) {
            return ['4x2', '4x4', 'Top', 'Automatic'];
        }

        return ['Base', 'Mid', 'Top', 'Automatic'];
    }

    private function bikeModelsByBrand(): array
    {
        return [
            'Hero' => ['Splendor Plus', 'HF Deluxe', 'Passion Plus', 'Glamour', 'Super Splendor', 'Xtreme 125R', 'Xtreme 160R 4V', 'Xpulse 200 4V', 'Karizma XMR', 'Mavrick 440'],
            'Honda' => ['Shine 100', 'Shine 125', 'SP125', 'Unicorn', 'Hornet 2.0', 'CB200X', 'CB350', 'Hness CB350', 'CB350RS', 'Activa 6G', 'Dio 125'],
            'Bajaj' => ['Platina 100', 'CT 110X', 'Pulsar 125', 'Pulsar N150', 'Pulsar N160', 'Pulsar N250', 'Pulsar NS200', 'Pulsar RS200', 'Dominar 250', 'Dominar 400', 'Avenger Street 160', 'Chetak'],
            'TVS' => ['Sport', 'Radeon', 'Raider 125', 'Apache RTR 160 4V', 'Apache RTR 200 4V', 'Apache RR 310', 'Ronin', 'Jupiter 110', 'Ntorq 125', 'iQube'],
            'Royal Enfield' => ['Hunter 350', 'Bullet 350', 'Classic 350', 'Meteor 350', 'Guerrilla 450', 'Himalayan 450', 'Interceptor 650', 'Continental GT 650', 'Super Meteor 650', 'Shotgun 650'],
            'Yamaha' => ['FZ-S Fi', 'FZ-X', 'MT-15 V2', 'R15 V4', 'R3', 'MT-03', 'Aerox 155', 'Fascino 125', 'RayZR 125'],
            'Suzuki' => ['Access 125', 'Burgman Street 125', 'Avenis 125', 'Gixxer', 'Gixxer SF', 'V-Strom SX', 'Hayabusa', 'Katana'],
            'KTM' => ['125 Duke', '200 Duke', '250 Duke', '390 Duke', 'RC 200', 'RC 390', '250 Adventure', '390 Adventure'],
            'Jawa' => ['42', '42 Bobber', 'Perak', 'Jawa 350'],
            'Yezdi' => ['Roadster', 'Scrambler', 'Adventure'],
            'Harley-Davidson' => ['X440', 'Nightster', 'Sportster S', 'Pan America 1250'],
            'BMW Motorrad' => ['G 310 R', 'G 310 GS', 'CE 02', 'F 850 GS', 'R 1250 GS'],
            'Triumph' => ['Speed 400', 'Scrambler 400 X', 'Speed Twin 900', 'Street Triple R', 'Tiger Sport 660'],
            'Kawasaki' => ['Ninja 300', 'Ninja 500', 'Ninja ZX-4R', 'Ninja ZX-6R', 'Z650', 'Versys 650'],
            'Ducati' => ['Scrambler Icon', 'Monster', 'Multistrada V2', 'Panigale V2', 'Diavel V4'],
            'Benelli' => ['TRK 251', 'TRK 502', 'Leoncino 500', 'Imperiale 400'],
            'Ola' => ['S1 X', 'S1 Air', 'S1 Pro'],
            'Ather' => ['450S', '450X', 'Rizta'],
            'Revolt' => ['RV1', 'RV400', 'RV400 BRZ'],
            'Other' => ['Other / Custom'],
        ];
    }

    private function bikeVariantsByModel(): array
    {
        $variants = [];

        foreach ($this->bikeModelsByBrand() as $models) {
            foreach ($models as $model) {
                $variants[$model] = $this->defaultBikeVariants($model);
            }
        }

        $overrides = [
            'Splendor Plus' => ['Drum', 'i3S Drum', 'XTEC Drum', 'XTEC Disc'],
            'HF Deluxe' => ['Kick Start', 'Self Start', 'Alloy Wheel'],
            'Shine 125' => ['Drum', 'Disc', 'OBD2B'],
            'SP125' => ['Drum', 'Disc', 'Deluxe'],
            'Unicorn' => ['Standard', 'Deluxe'],
            'Activa 6G' => ['Standard', 'Deluxe', 'H-Smart'],
            'Dio 125' => ['Standard', 'Smart'],
            'Pulsar NS200' => ['Single Channel ABS', 'Dual Channel ABS', 'Bluetooth'],
            'Pulsar N160' => ['Single Channel ABS', 'Dual Channel ABS', 'USD'],
            'Pulsar N250' => ['Dual Channel ABS', 'Bluetooth', 'USD'],
            'Dominar 400' => ['Touring', 'Standard'],
            'Chetak' => ['2903', '3201', 'Premium'],
            'Apache RTR 160 4V' => ['Drum', 'Disc', 'Special Edition'],
            'Apache RTR 200 4V' => ['Single Channel ABS', 'Dual Channel ABS', 'Top'],
            'Apache RR 310' => ['Standard', 'BTO Kit 1', 'BTO Kit 2'],
            'Raider 125' => ['Drum', 'Disc', 'SmartXonnect'],
            'Jupiter 110' => ['Drum', 'Drum Alloy', 'Disc'],
            'Ntorq 125' => ['Drum', 'Race Edition', 'XT'],
            'iQube' => ['2.2 kWh', '3.4 kWh', 'ST'],
            'Classic 350' => ['Redditch', 'Halcyon', 'Signals', 'Dark', 'Chrome'],
            'Bullet 350' => ['Military', 'Standard', 'Black Gold'],
            'Meteor 350' => ['Fireball', 'Stellar', 'Supernova'],
            'Himalayan 450' => ['Base', 'Pass', 'Summit'],
            'Interceptor 650' => ['Standard', 'Custom', 'Blackout'],
            'Continental GT 650' => ['Standard', 'Alloy Wheel', 'Blackout'],
            'Super Meteor 650' => ['Astral', 'Interstellar', 'Celestial'],
            'R15 V4' => ['Metallic', 'Dark Knight', 'M', 'Racing'],
            'MT-15 V2' => ['Standard', 'Deluxe', 'MotoGP Edition'],
            'Aerox 155' => ['Standard', 'S Edition'],
            'FZ-S Fi' => ['Standard', 'Deluxe', 'Bluetooth'],
            'Access 125' => ['Drum', 'Disc', 'Ride Connect'],
            'Burgman Street 125' => ['Standard', 'EX', 'Ride Connect'],
            'Avenis 125' => ['Standard', 'Race Edition'],
            '390 Duke' => ['Standard', 'Top'],
            '390 Adventure' => ['Standard', 'X', 'Top'],
            'RC 390' => ['Standard', 'GP Edition'],
            '42 Bobber' => ['Moonstone White', 'Mystic Copper', 'Jasper Red'],
            'Roadster' => ['Dark', 'Chrome', 'Top'],
            'X440' => ['Denim', 'Vivid', 'S'],
            'G 310 R' => ['Standard', 'Sport'],
            'Speed 400' => ['Standard', 'Top'],
            'Scrambler 400 X' => ['Standard', 'Top'],
            'Ninja 300' => ['Standard', 'KRT'],
            'Ninja 500' => ['Standard', 'SE'],
            'Monster' => ['Standard', 'Plus', 'SP'],
            'S1 Pro' => ['3 kWh', '4 kWh', 'Gen 2'],
            '450X' => ['2.9 kWh', '3.7 kWh', 'Pro Pack'],
            'Rizta' => ['S', 'Z 2.9 kWh', 'Z 3.7 kWh'],
            'RV400' => ['Standard', 'Premium'],
            'Other / Custom' => ['Standard', 'Top'],
        ];

        foreach ($overrides as $model => $modelVariants) {
            $variants[$model] = $modelVariants;
        }

        return $variants;
    }

    private function defaultBikeVariants(string $model): array
    {
        if (preg_match('/S1|iQube|Chetak|450|Rizta|RV|CE 02/i', $model)) {
            return ['Base Battery', 'Mid Battery', 'Top Battery'];
        }

        if (preg_match('/Activa|Dio|Jupiter|Ntorq|Access|Burgman|Avenis|Aerox|Fascino|RayZR/i', $model)) {
            return ['Drum', 'Disc', 'Top'];
        }

        return ['Standard', 'ABS', 'Top'];
    }

    private function uniqueSlug(string $base, int $categoryId): string
    {
        $slug = Str::slug($base);
        $counter = 2;

        while (CustomField::where('category_id', $categoryId)->where('slug', $slug)->exists()) {
            $slug = Str::slug($base).'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
