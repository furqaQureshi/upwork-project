<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\SubscriptionPackagePurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display profile details page.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $activeSubscription = $this->activeSubscriptionFor($user->id);

        return view('profile.edit', [
            'user' => $user,
            'activeSubscription' => $activeSubscription,
            'hasActiveSubscription' => $activeSubscription !== null,
        ]);
    }

    /**
     * Display the profile edit form page.
     */
    public function editForm(Request $request): View
    {
        $user = $request->user();
        $activeSubscription = $this->activeSubscriptionFor($user->id);

        return view('profile.edit-form', [
            'user' => $user,
            'activeSubscription' => $activeSubscription,
            'hasActiveSubscription' => $activeSubscription !== null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $applySellerVerification = $request->boolean('apply_seller_verification');

        $user->fill(Arr::except($validated, ['verification_document', 'apply_seller_verification']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($request->hasFile('verification_document')) {
            if ($user->verification_document_path) {
                Storage::disk('public')->delete((string) $user->verification_document_path);
            }

            $user->verification_document_path = $request->file('verification_document')->store('seller-documents/'.$user->id, 'public');
            $user->seller_verification_status = 'pending';
            $user->seller_verified_at = null;
            $user->seller_verification_note = null;
        } elseif ($applySellerVerification && $user->verification_document_path && $user->seller_verification_status !== 'approved') {
            $user->seller_verification_status = 'pending';
            $user->seller_verified_at = null;
            $user->seller_verification_note = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    private function activeSubscriptionFor(int $userId): ?SubscriptionPackagePurchase
    {
        return SubscriptionPackagePurchase::query()
            ->with('subscriptionPackage.category.parent')
            ->where('user_id', $userId)
            ->active()
            ->orderByRaw('CASE WHEN package_expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('package_expires_at')
            ->orderByDesc('id')
            ->first();
    }
}
