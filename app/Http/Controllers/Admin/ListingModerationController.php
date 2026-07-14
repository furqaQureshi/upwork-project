<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingReport;
use App\Notifications\ListingDeletedByAdminNotification;
use App\Notifications\ListingModeratedNotification;
use App\Services\WebPush\WebPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ListingModerationController extends Controller
{
    public function index(Request $request): View
    {
        $listings = Listing::query()
            ->with(['user', 'category', 'images'])
            ->withCount([
                'reports as open_reports_count' => function ($builder): void {
                    $builder->where('status', 'open');
                },
            ])
            ->when($request->filled('status'), function ($builder) use ($request): void {
                $builder->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $term = $request->string('q')->toString();
                $builder->where(function ($nested) use ($term): void {
                    $nested
                        ->where('title', 'like', "%{$term}%")
                        ->orWhere('city', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.listings.index', [
            'listings' => $listings,
        ]);
    }

    public function show(Listing $listing): View
    {
        $listing->load([
            'user',
            'category',
            'images',
            'reports' => function ($builder): void {
                $builder->with('user')->latest();
            },
        ]);

        return view('admin.listings.show', [
            'listing' => $listing,
        ]);
    }

    public function export(Request $request)
    {
        $query = Listing::query()
            ->with(['user', 'category']);

        // Apply same filters as index
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($nested) use ($term): void {
                $nested
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('city', 'like', "%{$term}%");
            });
        }

        $listings = $query->latest()->get();

        $csv = "Title,Category,Seller,City,Price,Condition,Status,Views,Featured,Published,Created\n";

        foreach ($listings as $listing) {
            $csv .= sprintf(
                '"%s","%s","%s","%s",₹%s,"%s","%s",%d,"%s",%s,%s',
                str_replace('"', '""', $listing->title),
                $listing->category?->name ?? 'N/A',
                str_replace('"', '""', $listing->user?->name ?? 'N/A'),
                $listing->city,
                number_format((float) $listing->price, 2),
                ucfirst($listing->condition),
                ucfirst($listing->status),
                $listing->views,
                $listing->is_featured ? 'Yes' : 'No',
                $listing->published_at?->format('M d, Y') ?? 'Not published',
                $listing->created_at->format('M d, Y')
            ) . "\n";
        }

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="listings_' . now()->format('Y-m-d_H-i-s') . '.csv"');
    }

    public function approve(Listing $listing, WebPushService $webPushService): RedirectResponse
    {
        $listing->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'published_at' => now(),
        ]);

        $listing->loadMissing('user');
        $notification = new ListingModeratedNotification(
            listingId: $listing->id,
            listingSlug: $listing->slug,
            listingTitle: $listing->title,
            status: 'approved',
        );

        if ($listing->user) {
            $listing->user->notify($notification);
            $webPushService->sendToUser($listing->user, $notification->toWebPushPayload());
        }

        return back()->with('status', 'Listing approved successfully.');
    }

    public function reject(
        Request $request,
        Listing $listing,
        WebPushService $webPushService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        $listing->update([
            'status' => 'rejected',
            'is_featured' => false,
            'featured_until' => null,
            'rejection_reason' => $validated['reason'],
            'published_at' => null,
        ]);

        $listing->loadMissing('user');
        $notification = new ListingModeratedNotification(
            listingId: $listing->id,
            listingSlug: $listing->slug,
            listingTitle: $listing->title,
            status: 'rejected',
            reason: $validated['reason'],
        );

        if ($listing->user) {
            $listing->user->notify($notification);
            $webPushService->sendToUser($listing->user, $notification->toWebPushPayload());
        }

        return back()->with('status', 'Listing rejected with feedback.');
    }

    public function toggleFeatured(Listing $listing): RedirectResponse
    {
        if ($listing->status !== 'approved') {
            return back()->with('status', 'Only approved listings can be featured.');
        }

        $nextFeaturedState = ! $listing->is_featured;

        $listing->update([
            'is_featured' => $nextFeaturedState,
            'featured_until' => null,
        ]);

        return back()->with('status', 'Featured status updated.');
    }

    public function destroy(
        Request $request,
        Listing $listing,
        WebPushService $webPushService
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $this->deleteListingWithNotification(
            listing: $listing,
            reason: $validated['reason'] ?? null,
            webPushService: $webPushService,
        );

        return back()->with('status', 'Listing deleted successfully.');
    }

    public function bulkDestroy(
        Request $request,
        WebPushService $webPushService
    ): RedirectResponse {
        $validated = $request->validate([
            'listing_ids' => ['required', 'array', 'min:1'],
            'listing_ids.*' => ['required', 'integer', 'exists:listings,id'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $listingIds = array_values(array_unique(array_map('intval', (array) ($validated['listing_ids'] ?? []))));
        $reason = $validated['reason'] ?? null;

        $listings = Listing::query()
            ->whereIn('id', $listingIds)
            ->get();

        foreach ($listings as $listing) {
            $this->deleteListingWithNotification(
                listing: $listing,
                reason: $reason,
                webPushService: $webPushService,
            );
        }

        $deletedCount = $listings->count();

        return back()->with('status', "{$deletedCount} listing(s) deleted successfully.");
    }

    public function resolveReport(ListingReport $report): RedirectResponse
    {
        $report->update([
            'status' => 'resolved',
        ]);

        return back()->with('status', 'Report marked as resolved.');
    }

    public function dismissReport(ListingReport $report): RedirectResponse
    {
        $report->update([
            'status' => 'dismissed',
        ]);

        return back()->with('status', 'Report dismissed.');
    }

    private function deleteListingWithNotification(
        Listing $listing,
        ?string $reason,
        WebPushService $webPushService
    ): void {
        $listing->loadMissing('user');

        $imagePaths = $listing->images()
            ->pluck('path')
            ->filter(fn ($path): bool => is_string($path) && trim($path) !== '')
            ->values()
            ->all();

        $customFieldFilePaths = $listing->customFieldValues()
            ->whereHas('customField', function ($builder): void {
                $builder->where('field_type', 'file');
            })
            ->whereNotNull('value_text')
            ->pluck('value_text')
            ->filter(fn ($path): bool => is_string($path) && trim($path) !== '')
            ->values()
            ->all();

        $paths = array_values(array_unique(array_merge($imagePaths, $customFieldFilePaths)));

        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }

        $owner = $listing->user;
        $listingId = (int) $listing->id;
        $listingTitle = (string) $listing->title;

        $listing->delete();

        if (! $owner) {
            return;
        }

        $notification = new ListingDeletedByAdminNotification(
            listingId: $listingId,
            listingTitle: $listingTitle,
            reason: $reason,
        );

        $owner->notify($notification);
        $webPushService->sendToUser($owner, $notification->toWebPushPayload());
    }
}
