<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class CarSellerVerificationPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find the Cars category
        $carsCategory = Category::where('slug', 'cars')->first();
        $mobilesCategory = Category::where('slug', 'mobiles')->first();
        
        if (!$carsCategory) {
            $this->command->warn('Cars category not found. Please seed categories first.');
            return;
        }

        // Create Car Seller Verification Bronze Package
        SubscriptionPackage::updateOrCreate(
            [
                'name' => 'Car Seller Verification - Basic',
                'package_type' => 'listing',
                'is_seller_verification' => true,
            ],
            [
                'price' => 99.00,
                'discount_percent' => 0,
                'final_price' => 99.00,
                'package_duration_type' => 'unlimited',
                'package_duration_days' => null,
                'item_limit_type' => 'unlimited',
                'item_limit_count' => null,
                'listing_duration_type' => 'standard',
                'listing_duration_days' => 30,
                'category_scope' => 'specific',
                'category_id' => $carsCategory->id,
                'required_documents' => [
                    'company_certificate',
                    'aadhar',
                    'pan',
                ],
                'seller_tier' => 'car_verified',
                'seller_badge_label' => 'CAR VERIFIED SELLER',
                'key_points' => [
                    'Seller verification badge on profile',
                    'Increased buyer trust and visibility',
                    'Priority support',
                    'Annual renewal required',
                ],
                'is_active' => true,
            ]
        );

        // Create Car Seller Verification Silver Package
        SubscriptionPackage::updateOrCreate(
            [
                'name' => 'Verified Seller - Basic',
                'package_type' => 'listing',
                'is_seller_verification' => true,
            ],
            [
                'price' => 199.00,
                'discount_percent' => 0,
                'final_price' => 199.00,
                'package_duration_type' => 'unlimited',
                'package_duration_days' => null,
                'item_limit_type' => 'limited',
                'item_limit_count' => 50,
                'listing_duration_type' => 'custom',
                'listing_duration_days' => 60,
                'category_scope' => 'specific',
                'category_id' => $mobilesCategory?->id,
                'required_documents' => [
                    'company_certificate',
                    'aadhar',
                    'pan',
                ],
                'seller_tier' => 'verified',
                'seller_badge_label' => 'VERIFIED SELLER',
                'key_points' => [
                    'Verified seller badge',
                    'Up to 50 active listings in selected category',
                    'Extended listing duration (60 days)',
                    'Priority customer support',
                    'Monthly analytics dashboard',
                    'Faster trust approval for buyers',
                ],
                'allows_call' => true,
                'is_active' => true,
            ]
        );

        // Create Premium Verified Seller Package
        SubscriptionPackage::updateOrCreate(
            [
                'name' => 'Premium Seller - Global',
                'package_type' => 'listing',
                'is_seller_verification' => true,
            ],
            [
                'price' => 799.00,
                'discount_percent' => 0,
                'final_price' => 799.00,
                'package_duration_type' => 'unlimited',
                'package_duration_days' => null,
                'item_limit_type' => 'limited',
                'item_limit_count' => 200,
                'listing_duration_type' => 'custom',
                'listing_duration_days' => 90,
                'category_scope' => 'global',
                'category_id' => null,
                'required_documents' => [
                    'company_certificate',
                    'aadhar',
                    'pan',
                ],
                'seller_tier' => 'premium_verified',
                'seller_badge_label' => 'PREMIUM SELLER',
                'key_points' => [
                    'Premium seller badge across all approved categories',
                    'Up to 200 active listings',
                    'Extended listing duration (90 days)',
                    'Dedicated account manager',
                    'Advanced analytics dashboard',
                    'Priority support and faster lead handling',
                ],
                'allows_call' => true,
                'allows_ai' => true,
                'ai_usage_limit_type' => 'unlimited',
                'ai_usage_limit_count' => null,
                'is_active' => true,
            ]
        );

        $this->command->info('Car Seller Verification packages seeded successfully!');
    }
}
