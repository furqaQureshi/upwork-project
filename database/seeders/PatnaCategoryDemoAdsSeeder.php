<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;

class PatnaCategoryDemoAdsSeeder extends Seeder
{
    public function run(): void
    {
        $seller = User::firstOrCreate(
            ['email' => 'patna.demo.seller@unsell.test'],
            [
                'name' => 'Patna Demo Seller',
                'phone' => '+919900001111',
                'city' => 'Patna',
                'state' => 'Bihar',
                'email_verified_at' => now(),
                'password' => bcrypt('Seller@12345'),
            ]
        );

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        foreach ($categories as $category) {
            $listingSlug = 'demo-ad-patna-'.$category->id;

            Listing::updateOrCreate(
                ['slug' => $listingSlug],
                [
                    'user_id' => $seller->id,
                    'category_id' => $category->id,
                    'title' => 'Demo '.$category->name.' Ad - Patna',
                    'description' => 'Demo listing for '.$category->name.' in Patna, Bihar. This is seeded test content for category browsing and search.',
                    'price' => 9999,
                    'price_type' => 'negotiable',
                    'currency' => 'INR',
                    'condition' => 'used',
                    'city' => 'Patna',
                    'state' => 'Bihar',
                    'address' => 'Patna, Bihar, India',
                    'latitude' => 25.5940947,
                    'longitude' => 85.1375645,
                    'status' => 'approved',
                    'is_featured' => false,
                    'views' => 0,
                    'published_at' => now(),
                    'expires_at' => now()->addDays(90),
                    'youtube_url' => null,
                ]
            );
        }
    }
}
