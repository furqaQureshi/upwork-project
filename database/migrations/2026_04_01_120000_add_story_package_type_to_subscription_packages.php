<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement("CREATE TABLE subscription_packages_tmp (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(120) NOT NULL,
                package_type TEXT NOT NULL CHECK (package_type IN ('listing','featured','story')),
                price NUMERIC NOT NULL,
                discount_percent NUMERIC NOT NULL DEFAULT 0,
                final_price NUMERIC NOT NULL,
                package_duration_type TEXT NOT NULL DEFAULT 'limited' CHECK (package_duration_type IN ('limited','unlimited')),
                package_duration_days INTEGER,
                item_limit_type TEXT NOT NULL DEFAULT 'limited' CHECK (item_limit_type IN ('limited','unlimited')),
                item_limit_count INTEGER,
                listing_duration_type TEXT NOT NULL DEFAULT 'standard' CHECK (listing_duration_type IN ('standard','custom')),
                listing_duration_days INTEGER NOT NULL DEFAULT 30,
                category_scope TEXT NOT NULL DEFAULT 'global' CHECK (category_scope IN ('global','specific')),
                category_id INTEGER,
                key_points TEXT,
                required_documents TEXT,
                is_seller_verification INTEGER NOT NULL DEFAULT 0,
                seller_type VARCHAR(30),
                seller_tier VARCHAR(40),
                seller_badge_label VARCHAR(120),
                icon VARCHAR,
                allows_call INTEGER NOT NULL DEFAULT 0,
                allows_ai INTEGER NOT NULL DEFAULT 0,
                ai_usage_limit_type TEXT NOT NULL DEFAULT 'limited' CHECK (ai_usage_limit_type IN ('limited','unlimited')),
                ai_usage_limit_count INTEGER,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )");

            DB::statement("INSERT INTO subscription_packages_tmp (
                id,
                name,
                package_type,
                price,
                discount_percent,
                final_price,
                package_duration_type,
                package_duration_days,
                item_limit_type,
                item_limit_count,
                listing_duration_type,
                listing_duration_days,
                category_scope,
                category_id,
                key_points,
                required_documents,
                is_seller_verification,
                seller_type,
                seller_tier,
                seller_badge_label,
                icon,
                allows_call,
                allows_ai,
                ai_usage_limit_type,
                ai_usage_limit_count,
                is_active,
                created_at,
                updated_at
            )
            SELECT
                id,
                name,
                package_type,
                price,
                discount_percent,
                final_price,
                package_duration_type,
                package_duration_days,
                item_limit_type,
                item_limit_count,
                listing_duration_type,
                listing_duration_days,
                category_scope,
                category_id,
                key_points,
                required_documents,
                is_seller_verification,
                seller_type,
                seller_tier,
                seller_badge_label,
                icon,
                allows_call,
                allows_ai,
                ai_usage_limit_type,
                ai_usage_limit_count,
                is_active,
                created_at,
                updated_at
            FROM subscription_packages");

            DB::statement('DROP TABLE subscription_packages');
            DB::statement('ALTER TABLE subscription_packages_tmp RENAME TO subscription_packages');
            DB::statement('CREATE INDEX IF NOT EXISTS subscription_packages_package_type_is_active_index ON subscription_packages (package_type, is_active)');
            DB::statement('CREATE INDEX IF NOT EXISTS subscription_packages_category_scope_index ON subscription_packages (category_scope)');
            DB::statement('CREATE INDEX IF NOT EXISTS subscription_packages_seller_type_index ON subscription_packages (seller_type)');
            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        DB::statement("ALTER TABLE subscription_packages MODIFY package_type ENUM('listing','featured','story') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement("CREATE TABLE subscription_packages_tmp (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(120) NOT NULL,
                package_type TEXT NOT NULL CHECK (package_type IN ('listing','featured')),
                price NUMERIC NOT NULL,
                discount_percent NUMERIC NOT NULL DEFAULT 0,
                final_price NUMERIC NOT NULL,
                package_duration_type TEXT NOT NULL DEFAULT 'limited' CHECK (package_duration_type IN ('limited','unlimited')),
                package_duration_days INTEGER,
                item_limit_type TEXT NOT NULL DEFAULT 'limited' CHECK (item_limit_type IN ('limited','unlimited')),
                item_limit_count INTEGER,
                listing_duration_type TEXT NOT NULL DEFAULT 'standard' CHECK (listing_duration_type IN ('standard','custom')),
                listing_duration_days INTEGER NOT NULL DEFAULT 30,
                category_scope TEXT NOT NULL DEFAULT 'global' CHECK (category_scope IN ('global','specific')),
                category_id INTEGER,
                key_points TEXT,
                required_documents TEXT,
                is_seller_verification INTEGER NOT NULL DEFAULT 0,
                seller_type VARCHAR(30),
                seller_tier VARCHAR(40),
                seller_badge_label VARCHAR(120),
                icon VARCHAR,
                allows_call INTEGER NOT NULL DEFAULT 0,
                allows_ai INTEGER NOT NULL DEFAULT 0,
                ai_usage_limit_type TEXT NOT NULL DEFAULT 'limited' CHECK (ai_usage_limit_type IN ('limited','unlimited')),
                ai_usage_limit_count INTEGER,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME,
                updated_at DATETIME,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )");

            DB::statement("INSERT INTO subscription_packages_tmp (
                id,
                name,
                package_type,
                price,
                discount_percent,
                final_price,
                package_duration_type,
                package_duration_days,
                item_limit_type,
                item_limit_count,
                listing_duration_type,
                listing_duration_days,
                category_scope,
                category_id,
                key_points,
                required_documents,
                is_seller_verification,
                seller_type,
                seller_tier,
                seller_badge_label,
                icon,
                allows_call,
                allows_ai,
                ai_usage_limit_type,
                ai_usage_limit_count,
                is_active,
                created_at,
                updated_at
            )
            SELECT
                id,
                name,
                CASE WHEN package_type = 'story' THEN 'featured' ELSE package_type END,
                price,
                discount_percent,
                final_price,
                package_duration_type,
                package_duration_days,
                item_limit_type,
                item_limit_count,
                listing_duration_type,
                listing_duration_days,
                category_scope,
                category_id,
                key_points,
                required_documents,
                is_seller_verification,
                seller_type,
                seller_tier,
                seller_badge_label,
                icon,
                allows_call,
                allows_ai,
                ai_usage_limit_type,
                ai_usage_limit_count,
                is_active,
                created_at,
                updated_at
            FROM subscription_packages");

            DB::statement('DROP TABLE subscription_packages');
            DB::statement('ALTER TABLE subscription_packages_tmp RENAME TO subscription_packages');
            DB::statement('CREATE INDEX IF NOT EXISTS subscription_packages_package_type_is_active_index ON subscription_packages (package_type, is_active)');
            DB::statement('CREATE INDEX IF NOT EXISTS subscription_packages_category_scope_index ON subscription_packages (category_scope)');
            DB::statement('CREATE INDEX IF NOT EXISTS subscription_packages_seller_type_index ON subscription_packages (seller_type)');
            DB::statement('PRAGMA foreign_keys = ON');

            return;
        }

        DB::table('subscription_packages')
            ->where('package_type', 'story')
            ->update(['package_type' => 'featured']);

        DB::statement("ALTER TABLE subscription_packages MODIFY package_type ENUM('listing','featured') NOT NULL");
    }
};
