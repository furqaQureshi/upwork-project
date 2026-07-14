<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class CustomerSubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter Sell Pack',
                'package_type' => 'listing',
                'price' => 99,
                'discount_percent' => 0,
                'final_price' => 99,
                'package_duration_type' => 'limited',
                'package_duration_days' => 30,
                'item_limit_type' => 'limited',
                'item_limit_count' => 5,
                'listing_duration_type' => 'standard',
                'listing_duration_days' => 30,
                'allows_call' => true,
                'allows_ai' => false,
                'ai_usage_limit_type' => 'limited',
                'ai_usage_limit_count' => 0,
                'key_points' => [
                    'Post 5 ads',
                    'Buyer calls unlocked',
                    '30 day validity',
                ],
            ],
            [
                'name' => 'Power Seller Pack',
                'package_type' => 'listing',
                'price' => 399,
                'discount_percent' => 25,
                'final_price' => 299,
                'package_duration_type' => 'limited',
                'package_duration_days' => 45,
                'item_limit_type' => 'limited',
                'item_limit_count' => 25,
                'listing_duration_type' => 'custom',
                'listing_duration_days' => 45,
                'allows_call' => true,
                'allows_ai' => true,
                'ai_usage_limit_type' => 'limited',
                'ai_usage_limit_count' => 10,
                'key_points' => [
                    'Post 25 ads',
                    'AI listing helper',
                    'Longer live ads',
                ],
            ],
            [
                'name' => 'Featured Boost Pack',
                'package_type' => 'featured',
                'price' => 249,
                'discount_percent' => 0,
                'final_price' => 249,
                'package_duration_type' => 'limited',
                'package_duration_days' => 30,
                'item_limit_type' => 'limited',
                'item_limit_count' => 5,
                'listing_duration_type' => 'custom',
                'listing_duration_days' => 7,
                'allows_call' => false,
                'allows_ai' => false,
                'ai_usage_limit_type' => 'limited',
                'ai_usage_limit_count' => 0,
                'key_points' => [
                    'Boost 5 ads',
                    'Featured placement',
                    '7 days per boost',
                ],
            ],
            [
                'name' => 'Verified Story Pack',
                'package_type' => 'story',
                'price' => 199,
                'discount_percent' => 0,
                'final_price' => 199,
                'package_duration_type' => 'limited',
                'package_duration_days' => 30,
                'item_limit_type' => 'limited',
                'item_limit_count' => 10,
                'listing_duration_type' => 'custom',
                'listing_duration_days' => 3,
                'allows_call' => false,
                'allows_ai' => false,
                'ai_usage_limit_type' => 'limited',
                'ai_usage_limit_count' => 0,
                'key_points' => [
                    'Publish seller stories',
                    '10 story posts',
                    'Verified seller tools',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPackage::updateOrCreate(
                ['name' => $plan['name']],
                $plan + [
                    'category_scope' => 'global',
                    'category_id' => null,
                    'is_seller_verification' => false,
                    'required_documents' => [],
                    'is_active' => true,
                ]
            );
        }
    }
}
