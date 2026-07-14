<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'users' => User::query()->count(),
            'blocked_users' => User::query()->where('is_blocked', true)->count(),
            'pending_listings' => Listing::query()->where('status', 'pending')->count(),
            'approved_listings' => Listing::query()->where('status', 'approved')->count(),
            'sold_listings' => Listing::query()->where('status', 'sold')->count(),
            'open_reports' => ListingReport::query()->where('status', 'open')->count(),
        ];

        $recentListings = Listing::query()
            ->with(['user', 'category'])
            ->latest()
            ->take(8)
            ->get();

        $recentUsers = User::query()
            ->latest()
            ->take(8)
            ->get();

        $latestReports = ListingReport::query()
            ->with(['listing', 'user'])
            ->where('status', 'open')
            ->latest()
            ->take(8)
            ->get();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentListings' => $recentListings,
            'recentUsers' => $recentUsers,
            'latestReports' => $latestReports,
        ]);
    }
}
