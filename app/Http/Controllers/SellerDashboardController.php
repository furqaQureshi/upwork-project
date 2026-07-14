<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\SellerVerification;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerDashboardController extends Controller
{
    /**
     * Seller dashboard - main overview
     */
    public function index(Request $request): View
    {
        $user = auth()->user();

        // Verification Status
        $verification = $user->primaryApprovedSellerVerification();
        $pendingVerification = $user->sellerVerifications()
            ->where('status', 'pending')
            ->latest()
            ->first();

        // Seller Listings
        $totalListings = $user->listings()->where('status', 'active')->count();
        $soldListings = $user->listings()->where('status', 'sold')->count();
        $draftListings = $user->listings()->where('status', 'draft')->count();
        $expiredListings = $user->listings()->where('status', 'expired')->count();

        // Recent Activity
        $recentListings = $user->listings()
            ->latest('published_at')
            ->limit(5)
            ->get();

        // Messages/Conversations
        $unreadConversations = $user->conversationsAsSeller()
            ->where('last_message_by', '!=', $user->id)
            ->where(function ($q) {
                $q->whereNull('last_read_at')
                  ->orWhereRaw('last_message_at > last_read_at');
            })
            ->count();

        // Package Info
        $activePackage = $user->activeSellerPackage;
        $packageExpiry = null;
        if ($activePackage) {
            $latestPurchase = $user->subscriptionPackagePurchases()
                ->where('subscription_package_id', $activePackage->id)
                ->latest('purchased_at')
                ->first();
            if ($latestPurchase) {
                $packageExpiry = $latestPurchase->calculateExpiryDate();
            }
        }

        // Stats
        $stats = [
            'total_listings' => $totalListings,
            'sold_listings' => $soldListings,
            'draft_listings' => $draftListings,
            'expired_listings' => $expiredListings,
            'unread_messages' => $unreadConversations,
            'seller_tier' => $user->seller_type ?? 'Unverified',
            'verification_status' => $user->seller_verification_status ?? 'Pending',
        ];

        return view('seller.dashboard', [
            'user' => $user,
            'verification' => $verification,
            'pendingVerification' => $pendingVerification,
            'stats' => $stats,
            'recentListings' => $recentListings,
            'activePackage' => $activePackage,
            'packageExpiry' => $packageExpiry,
            'isVerified' => $user->is_seller_verified,
        ]);
    }

    /**
     * Seller analytics and earnings
     */
    public function analytics(Request $request): View
    {
        $user = auth()->user();

        $period = $request->query('period', '30'); // days
        $startDate = now()->subDays($period);

        // Listing Performance
        $listingStats = $user->listings()
            ->selectRaw('
                COUNT(*) as total_listings,
                SUM(CASE WHEN status = "sold" THEN 1 ELSE 0 END) as sold_count,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_count
            ')
            ->where('created_at', '>=', $startDate)
            ->first();

        // Views & Interactions
        $totalViews = $user->listings()
            ->where('created_at', '>=', $startDate)
            ->sum('view_count') ?? 0;

        $totalInteractions = $user->listings()
            ->where('created_at', '>=', $startDate)
            ->sum('interaction_count') ?? 0;

        // Messages
        $totalMessages = $user->conversationsAsSeller()
            ->where('created_at', '>=', $startDate)
            ->count();

        // Revenue (if tracking available)
        $totalRevenue = $user->conversationsAsSeller()
            ->where('created_at', '>=', $startDate)
            ->where('status', 'completed')
            ->count();

        // Verification History
        $verificationHistory = $user->sellerVerifications()
            ->with('category', 'subscriptionPackage')
            ->latest()
            ->get();

        // Package History
        $packageHistory = $user->subscriptionPackagePurchases()
            ->with('subscriptionPackage')
            ->latest('purchased_at')
            ->limit(10)
            ->get();

        return view('seller.analytics', [
            'user' => $user,
            'period' => $period,
            'listingStats' => $listingStats,
            'totalViews' => $totalViews,
            'totalInteractions' => $totalInteractions,
            'totalMessages' => $totalMessages,
            'totalRevenue' => $totalRevenue,
            'verificationHistory' => $verificationHistory,
            'packageHistory' => $packageHistory,
        ]);
    }

    /**
     * Seller verification management
     */
    public function verification(Request $request): View
    {
        $user = auth()->user();

        $verifications = $user->sellerVerifications()
            ->with('category', 'subscriptionPackage', 'documents')
            ->latest()
            ->get();

        $availablePackages = \App\Models\SubscriptionPackage::where('is_seller_verification', true)
            ->with('category')
            ->where('is_active', true)
            ->get();

        return view('seller.verification', [
            'user' => $user,
            'verifications' => $verifications,
            'availablePackages' => $availablePackages,
            'isVerified' => $user->is_seller_verified,
        ]);
    }

    /**
     * Seller profile and settings
     */
    public function profile(Request $request): View
    {
        $user = auth()->user();

        return view('seller.profile', [
            'user' => $user,
        ]);
    }

    /**
     * Update seller profile
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'business_name' => 'nullable|string|max:255',
            'business_description' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'business_hours' => 'nullable|json',
            'response_time' => 'nullable|integer',
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }
}
