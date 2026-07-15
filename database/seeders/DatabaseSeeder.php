<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AppSettingsSeeder::class,
            CategorySeeder::class,
            AdminUserSeeder::class,
            MobileBrandModelSeeder::class,
            PhoneMakeModelMatchTypeSeeder::class,
            VehicleBrandModelVariantSeeder::class,
            CommercialVehicleSeeder::class,
            ElectronicsCategoryCatalogSeeder::class,
            ElectronicsAccessorySeeder::class,
            CategoryLogoSeeder::class,
            CustomerSubscriptionPackageSeeder::class,
            CarSellerVerificationPackageSeeder::class,
            PatnaCategoryDemoAdsSeeder::class,
        ]);

        if ((bool) env('SEED_UNISELL_FEATURED_ADS', false)) {
            $this->call([
                UnisellFeaturedAdsSeeder::class,
            ]);
        }

        $seller = User::firstOrCreate(
            ['email' => 'seller@unsell.test'],
            [
                'name' => 'Demo Seller',
                'phone' => '8888888888',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'email_verified_at' => now(),
                'password' => bcrypt('Seller@12345'),
            ]
        );

        $categoryIds = Category::query()->pluck('id', 'name');

        $samples = [
            [
                'title' => 'iPhone 13 128GB - Excellent Condition',
                'description' => 'Single owner iPhone 13 in excellent condition. Battery health 89%. Includes original box and charger.',
                'price' => 42000,
                'condition' => 'used',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'category' => 'Mobiles',
                'status' => 'approved',
                'is_featured' => true,
            ],
            [
                'title' => 'Royal Enfield Classic 350 2022 Model',
                'description' => 'Well-maintained bike with service records. Low mileage and recently serviced.',
                'price' => 165000,
                'condition' => 'used',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'category' => 'Bikes',
                'status' => 'approved',
                'is_featured' => false,
            ],
            [
                'title' => '2BHK Apartment for Rent - Whitefield',
                'description' => 'Spacious 2BHK apartment with covered parking and 24x7 security near tech parks.',
                'price' => 32000,
                'condition' => 'new',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'category' => 'Property',
                'status' => 'pending',
                'is_featured' => false,
            ],
        ];

        foreach ($samples as $sample) {
            Listing::updateOrCreate(
                [
                    'slug' => Str::slug($sample['title']),
                ],
                [
                    'user_id' => $seller->id,
                    'category_id' => $categoryIds[$sample['category']] ?? Category::query()->value('id'),
                    'title' => $sample['title'],
                    'description' => $sample['description'],
                    'price' => $sample['price'],
                    'currency' => 'INR',
                    'condition' => $sample['condition'],
                    'city' => $sample['city'],
                    'state' => $sample['state'],
                    'status' => $sample['status'],
                    'is_featured' => $sample['is_featured'],
                    'published_at' => $sample['status'] === 'approved' ? now() : null,
                ]
            );
        }

        User::firstOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'email_verified_at' => now(),
            'password' => bcrypt('User@12345'),
        ]);
    }
}
