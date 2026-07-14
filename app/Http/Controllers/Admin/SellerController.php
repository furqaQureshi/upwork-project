<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\User;
use App\Services\WebPush\WebPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerController extends Controller
{
    public function index(Request $request): View
    {
        $baseSellerQuery = User::query()
            ->where('is_admin', false)
            ->whereHas('listings');

        $sellerStats = [
            'total' => (clone $baseSellerQuery)->count(),
            'active' => (clone $baseSellerQuery)->where('is_blocked', false)->count(),
            'blocked' => (clone $baseSellerQuery)->where('is_blocked', true)->count(),
            'verified' => (clone $baseSellerQuery)->where('seller_verification_status', 'approved')->count(),
        ];

        $sellers = User::query()
            ->where('is_admin', false)
            ->whereHas('listings')
            ->withCount([
                'listings',
                'listings as approved_listings_count' => fn ($builder) => $builder->where('status', 'approved'),
                'listings as pending_listings_count' => fn ($builder) => $builder->where('status', 'pending'),
                'listings as sold_listings_count' => fn ($builder) => $builder->where('status', 'sold'),
                'conversationsAsSeller as seller_chats_count',
                'pushSubscriptions as active_push_devices_count' => fn ($builder) => $builder->where('is_active', true),
            ])
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $term = $request->string('q')->toString();
                $builder->where(function ($nested) use ($term): void {
                    $nested
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%")
                        ->orWhere('state', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), function ($builder) use ($request): void {
                $status = $request->string('status')->toString();
                if ($status === 'active') {
                    $builder->where('is_blocked', false);
                }

                if ($status === 'blocked') {
                    $builder->where('is_blocked', true);
                }
            })
            ->when($request->filled('verification'), function ($builder) use ($request): void {
                $verification = $request->string('verification')->toString();

                if ($verification === 'verified') {
                    $builder->where('seller_verification_status', 'approved');
                }

                if ($verification === 'pending') {
                    $builder->where('seller_verification_status', 'pending');
                }

                if ($verification === 'rejected') {
                    $builder->where('seller_verification_status', 'rejected');
                }

                if ($verification === 'unsubmitted') {
                    $builder->where('seller_verification_status', 'unsubmitted');
                }
            })
            ->when($request->filled('listing_status'), function ($builder) use ($request): void {
                $listingStatus = $request->string('listing_status')->toString();
                if (! in_array($listingStatus, ['pending', 'approved', 'rejected', 'sold'], true)) {
                    return;
                }

                $builder->whereHas('listings', fn ($listings) => $listings->where('status', $listingStatus));
            })
            ->tap(function ($builder) use ($request): void {
                $sort = $request->string('sort')->toString();

                if ($sort === 'most-listings') {
                    $builder->orderByDesc('listings_count')->orderByDesc('id');
                    return;
                }

                if ($sort === 'active-recent') {
                    $builder->orderByDesc('last_seen_at')->orderByDesc('id');
                    return;
                }

                if ($sort === 'oldest') {
                    $builder->oldest();
                    return;
                }

                $builder->latest();
            })
            ->paginate(20)
            ->withQueryString();

        return view('admin.sellers.index', [
            'sellers' => $sellers,
            'sellerStats' => $sellerStats,
        ]);
    }

    public function show(Request $request, User $seller): View
    {
        $this->ensureSeller($seller);

        $seller->loadCount([
            'listings',
            'listings as approved_listings_count' => fn ($builder) => $builder->where('status', 'approved'),
            'listings as pending_listings_count' => fn ($builder) => $builder->where('status', 'pending'),
            'listings as rejected_listings_count' => fn ($builder) => $builder->where('status', 'rejected'),
            'listings as sold_listings_count' => fn ($builder) => $builder->where('status', 'sold'),
            'conversationsAsSeller as seller_chats_count',
            'pushSubscriptions as active_push_devices_count' => fn ($builder) => $builder->where('is_active', true),
        ]);

        $listings = Listing::query()
            ->with(['category', 'images'])
            ->withCount([
                'reports as open_reports_count' => fn ($builder) => $builder->where('status', 'open'),
            ])
            ->where('user_id', $seller->id)
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $term = $request->string('q')->toString();
                $builder->where(function ($nested) use ($term): void {
                    $nested
                        ->where('title', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('status'), function ($builder) use ($request): void {
                $status = $request->string('status')->toString();
                if (in_array($status, ['pending', 'approved', 'rejected', 'sold'], true)) {
                    $builder->where('status', $status);
                }
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.sellers.show', [
            'seller' => $seller,
            'listings' => $listings,
        ]);
    }

    public function toggleBlock(Request $request, User $seller): RedirectResponse
    {
        $this->ensureSeller($seller);

        if ($request->user()->id === $seller->id) {
            return back()->with('status', 'You cannot block your own account.');
        }

        $seller->update([
            'is_blocked' => ! $seller->is_blocked,
        ]);

        return back()->with('status', 'Seller status updated successfully.');
    }

    public function testPush(User $seller, WebPushService $webPushService): RedirectResponse
    {
        $this->ensureSeller($seller);

        $activeCount = $seller->pushSubscriptions()->where('is_active', true)->count();

        if ($activeCount === 0) {
            return back()->with('status', "No active push subscriptions found for {$seller->name}.");
        }

        $webPushService->sendToUser($seller, [
            'title' => 'Seller Alert Test',
            'body' => "Admin test notification sent to {$seller->name}.",
            'icon' => '/branding/unsell-icon-512.png',
            'badge' => '/branding/unsell-icon-512.png',
            'tag' => 'admin-seller-test-push',
            'data' => [
                'url' => '/listings',
                'type' => 'seller-test',
            ],
        ]);

        return back()->with('status', "Test push sent to {$seller->name} ({$activeCount} device(s)).");
    }

    public function approveVerification(User $seller): RedirectResponse
    {
        $this->ensureSeller($seller);

        if (! $seller->verification_document_path) {
            return back()->with('status', 'Seller has not submitted any verification document yet.');
        }

        $seller->update([
            'seller_verification_status' => 'approved',
            'seller_verified_at' => now(),
            'seller_verification_note' => null,
        ]);

        return back()->with('status', 'Seller document verification approved successfully.');
    }

    public function rejectVerification(Request $request, User $seller): RedirectResponse
    {
        $this->ensureSeller($seller);

        if (! $seller->verification_document_path) {
            return back()->with('status', 'Seller has not submitted any verification document yet.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:300'],
        ]);

        $seller->update([
            'seller_verification_status' => 'rejected',
            'seller_verified_at' => null,
            'seller_verification_note' => $validated['reason'],
        ]);

        return back()->with('status', 'Seller document verification rejected with feedback.');
    }

    private function ensureSeller(User $seller): void
    {
        if ($seller->is_admin || ! $seller->listings()->exists()) {
            abort(404, 'Seller not found.');
        }
    }
}
