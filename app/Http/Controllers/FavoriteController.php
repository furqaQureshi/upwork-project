<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request, Listing $listing): RedirectResponse
    {
        if ($listing->status !== 'approved' && ! $listing->isOwnedBy($request->user())) {
            return back()->with('status', 'This listing is not available to favorite.');
        }

        $alreadyFavorited = $request->user()
            ->favoriteListings()
            ->where('listings.id', $listing->id)
            ->exists();

        if ($alreadyFavorited) {
            $request->user()->favoriteListings()->detach($listing->id);

            return back()->with('status', 'Removed from favorites.');
        }

        $request->user()->favoriteListings()->attach($listing->id);

        return back()->with('status', 'Added to favorites.');
    }
}
