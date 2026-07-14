<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPackagePurchase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    /**
     * Display a listing of subscribers.
     */
    public function index(Request $request)
    {
        $query = SubscriptionPackagePurchase::with(['user', 'package', 'package.category'])
            ->orderBy('created_at', 'desc');

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where(function (Builder $q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
            } elseif ($status === 'expired') {
                $query->where('expires_at', '<=', now());
            } elseif ($status === 'expiring_soon') {
                $query->whereBetween('expires_at', [now(), now()->addDays(7)]);
            }
        }

        // Package type filter
        if ($request->filled('package_type')) {
            $packageType = $request->input('package_type');
            $query->whereHas('package', function (Builder $q) use ($packageType) {
                $q->where('package_type', $packageType);
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $categoryId = $request->input('category');
            $query->whereHas('package', function (Builder $q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        $subscribers = $query->paginate(20);

        // Statistics
        $totalSubscribers = SubscriptionPackagePurchase::count();
        $newThisMonth = SubscriptionPackagePurchase::where('created_at', '>=', now()->startOfMonth())->count();
        $activeSubscriptions = SubscriptionPackagePurchase::where(function (Builder $q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })->count();
        $expiringSoon = SubscriptionPackagePurchase::whereBetween('expires_at', [now(), now()->addDays(7)])->count();
        $totalRevenue = SubscriptionPackagePurchase::sum('amount');

        $categories = Category::orderBy('name')->get();

        return view('admin.subscribers.index', compact(
            'subscribers',
            'categories',
            'totalSubscribers',
            'newThisMonth',
            'activeSubscriptions',
            'expiringSoon',
            'totalRevenue'
        ));
    }

    /**
     * Display the specified subscriber.
     */
    public function show(SubscriptionPackagePurchase $subscriber)
    {
        $subscriber->load(['user', 'package', 'package.category']);

        // Get available packages for upgrade
        $availablePackages = SubscriptionPackage::where('id', '!=', $subscriber->package_id)
            ->where('is_active', true)
            ->orderBy('final_price', 'asc')
            ->get();

        return view('admin.subscribers.show', compact('subscriber', 'availablePackages'));
    }

    /**
     * Renew subscription
     */
    public function renew(SubscriptionPackagePurchase $subscriber)
    {
        $newExpiryDate = now()->addDays($subscriber->package->package_duration_days ?? 365);

        $subscriber->update([
            'expires_at' => $newExpiryDate,
        ]);

        return redirect()->route('admin.subscribers.show', $subscriber)
            ->with('success', 'Subscription renewed successfully!');
    }

    /**
     * Upgrade subscription to a different package
     */
    public function upgrade(SubscriptionPackagePurchase $subscriber, SubscriptionPackage $package, Request $request)
    {
        $priceDifference = $package->final_price - $subscriber->package->final_price;

        // Create new subscription purchase
        $newSubscription = SubscriptionPackagePurchase::create([
            'user_id' => $subscriber->user_id,
            'package_id' => $package->id,
            'amount' => $priceDifference > 0 ? $priceDifference : 0,
            'payment_method' => 'admin_override',
            'payment_status' => 'paid',
            'transaction_id' => 'ADMIN_UPGRADE_' . $subscriber->id,
            'expires_at' => now()->addDays($package->package_duration_days ?? 365),
        ]);

        return redirect()->route('admin.subscribers.show', $newSubscription)
            ->with('success', 'Subscription upgraded successfully!');
    }

    /**
     * Cancel subscription
     */
    public function cancel(SubscriptionPackagePurchase $subscriber)
    {
        $subscriber->update([
            'expires_at' => now(),
        ]);

        return redirect()->route('admin.subscribers.index')
            ->with('success', 'Subscription cancelled successfully!');
    }

    /**
     * Export subscribers
     */
    public function export(Request $request)
    {
        $query = SubscriptionPackagePurchase::with(['user', 'package']);

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $subscribers = $query->get();

        $csv = "Name,Email,Phone,Package,Type,Amount,Purchase Date,Expiry Date,Status\n";

        foreach ($subscribers as $subscriber) {
            $isExpired = $subscriber->expires_at && $subscriber->expires_at->isPast();
            $status = $isExpired ? 'Expired' : 'Active';

            $csv .= sprintf(
                '"%s","%s","%s","%s","%s",₹%s,%s,%s,%s',
                $subscriber->user?->name ?? 'N/A',
                $subscriber->user?->email ?? 'N/A',
                $subscriber->user?->phone ?? 'N/A',
                $subscriber->package?->name ?? 'N/A',
                $subscriber->package?->package_type ?? 'N/A',
                number_format($subscriber->amount ?? 0, 2),
                $subscriber->created_at->format('M d, Y'),
                $subscriber->expires_at?->format('M d, Y') ?? 'No expiry',
                $status
            ) . "\n";
        }

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="subscribers_' . now()->format('Y-m-d') . '.csv"');
    }
}
