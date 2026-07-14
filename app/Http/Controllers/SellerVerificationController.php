<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SellerVerification;
use App\Models\SellerVerificationDocument;
use App\Models\SubscriptionPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SellerVerificationController extends Controller
{
    /**
     * Show the verification form for a seller
     */
    public function create(): View
    {
        $packages = SubscriptionPackage::where('is_seller_verification', true)
            ->where('is_active', true)
            ->with('category.parent')
            ->orderBy('final_price')
            ->get();

        $existingVerification = auth()->user()->sellerVerifications()
            ->latest()
            ->first();

        return view('seller-verification.create', [
            'packages' => $packages,
            'existingVerification' => $existingVerification,
        ]);
    }

    /**
     * Store the verification request
     */
    public function store(Request $request)
    {
        $request->validate([
            'subscription_package_id' => [
                'required',
                'exists:subscription_packages,id',
                function ($attribute, $value, $fail) {
                    $package = SubscriptionPackage::findOrFail($value);
                    if (!$package->is_seller_verification || ! $package->is_active) {
                        $fail('Invalid package selected.');
                    }
                },
            ],
            'company_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'aadhar' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'aadhar_number' => 'nullable|string|regex:/^\d{12}$/',
            'pan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'pan_number' => 'nullable|string|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
        ], [
            'aadhar_number.regex' => 'Aadhar number must be 12 digits',
            'pan_number.regex' => 'PAN must be in valid format (e.g., ABCDE1234F)',
        ]);

        $hasCompany = $request->hasFile('company_certificate');
        $hasAadhar = $request->hasFile('aadhar');
        $hasPan = $request->hasFile('pan');
        $selectedDocs = (int) $hasCompany + (int) $hasAadhar + (int) $hasPan;

        if ($selectedDocs !== 1) {
            return back()
                ->withErrors([
                    'documents' => 'Upload exactly one document: PAN, Aadhar, or Company/Firm Certificate.',
                ])
                ->withInput();
        }

        if ($hasAadhar && ! preg_match('/^\d{12}$/', (string) $request->input('aadhar_number'))) {
            return back()->withErrors([
                'aadhar_number' => 'Aadhar number must be 12 digits.',
            ])->withInput();
        }

        if ($hasPan && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper((string) $request->input('pan_number')))) {
            return back()->withErrors([
                'pan_number' => 'PAN must be in valid format (e.g., ABCDE1234F).',
            ])->withInput();
        }

        // Check if user already has an approved verification for this category
        $package = SubscriptionPackage::findOrFail((int) $request->subscription_package_id);

        $approvedVerification = auth()->user()->sellerVerifications()
            ->where('status', 'approved')
            ->whereHas('subscriptionPackage', function ($query) use ($package): void {
                $query->where('seller_tier', $package->seller_tier);
            })
            ->when($package->category_scope !== 'global', function ($query) use ($package): void {
                $query->where('category_id', $package->category_id);
            })
            ->first();

        if ($approvedVerification) {
            return redirect()->route('seller-verification.show', $approvedVerification)
                ->with('info', 'You already have an approved verification for this category.');
        }

        // Create new verification request
        $verification = SellerVerification::create([
            'user_id' => auth()->id(),
            'category_id' => $package->category_id,
            'subscription_package_id' => $request->subscription_package_id,
            'status' => 'pending',
        ]);

        auth()->user()->forceFill([
            'seller_verification_status' => 'pending',
            'seller_verification_note' => null,
        ])->saveQuietly();

        // Handle document uploads
        $documents = [
            'company_certificate' => ['company_certificate', null],
            'aadhar' => ['aadhar', $request->aadhar_number],
            'pan' => ['pan', strtoupper((string) $request->pan_number)],
        ];

        foreach ($documents as $key => [$docType, $docNumber]) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $path = $file->store("seller-verification/{$verification->id}", 'public');

                SellerVerificationDocument::create([
                    'seller_verification_id' => $verification->id,
                    'document_type' => $docType,
                    'document_path' => $path,
                    'document_number' => $docNumber,
                    'verification_status' => 'pending',
                ]);
            }
        }

        return redirect()->route('seller-verification.show', $verification)
            ->with('success', 'Verification request submitted successfully. Admin will review your documents soon.');
    }

    /**
     * Show verification status
     */
    public function show(SellerVerification $verification)
    {
        $this->authorize('view', $verification);

        return view('seller-verification.show', [
            'verification' => $verification,
        ]);
    }

    /**
     * Show list of user's verification requests
     */
    public function index()
    {
        $verifications = auth()->user()->sellerVerifications()
            ->with('category', 'subscriptionPackage', 'documents')
            ->latest()
            ->paginate(10);

        return view('seller-verification.index', [
            'verifications' => $verifications,
        ]);
    }

    /**
     * Re-upload a rejected document
     */
    public function updateDocument(Request $request, SellerVerification $verification, SellerVerificationDocument $document)
    {
        $this->authorize('update', $verification);

        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Only allow re-upload of rejected documents
        if ($document->verification_status !== 'rejected') {
            return back()->with('error', 'You can only re-upload rejected documents.');
        }

        // Delete old file
        if (Storage::disk('public')->exists($document->document_path)) {
            Storage::disk('public')->delete($document->document_path);
        }

        // Upload new file
        $file = $request->file('document');
        $path = $file->store("seller-verification/{$verification->id}", 'public');

        $document->update([
            'document_path' => $path,
            'verification_status' => 'pending',
            'verification_note' => null,
        ]);

        // Reset verification status to pending if all documents are now pending
        if ($verification->documents()->where('verification_status', '!=', 'pending')->count() === 0) {
            $verification->update(['status' => 'pending']);
        }

        return back()->with('success', 'Document re-uploaded successfully. Admin will review it again.');
    }

    /**
     * Delete verification request (only if pending or rejected)
     */
    public function destroy(SellerVerification $verification)
    {
        $this->authorize('delete', $verification);

        if (!in_array($verification->status, ['pending', 'rejected'])) {
            return back()->with('error', 'You can only delete pending or rejected verification requests.');
        }

        // Delete all associated documents
        foreach ($verification->documents as $document) {
            $document->deleteDocument();
        }

        $verification->delete();

        return redirect()->route('seller-verification.index')
            ->with('success', 'Verification request deleted successfully.');
    }

    /**
     * Export verification details (PDF)
     */
    public function export(SellerVerification $verification)
    {
        $this->authorize('view', $verification);

        // Implementation would use a PDF generation library like TCPDF or DomPDF
        // This is a placeholder
        return response()->json(['message' => 'Export feature coming soon']);
    }
}
