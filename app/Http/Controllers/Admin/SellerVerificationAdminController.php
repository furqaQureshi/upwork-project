<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerVerification;
use App\Models\SellerVerificationDocument;
use App\Services\SellerVerificationNotificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class SellerVerificationAdminController extends Controller
{
    /**
     * List all verification requests (pending, approved, rejected)
     */
    public function index(Request $request): View
    {
        $this->authorize('admin');

        $query = SellerVerification::with('user', 'category', 'subscriptionPackage', 'documents');

        // Filter by status
        if ($request->has('status') && in_array($request->status, ['pending', 'approved', 'rejected'])) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by user name or email
        if ($request->has('search') && $request->search) {
            $search = '%' . $request->search . '%';
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('email', 'like', $search);
            });
        }

        $verifications = $query->latest()->paginate(20);

        return view('admin.seller-verification.index', [
            'verifications' => $verifications,
        ]);
    }

    /**
     * Show verification details for review
     */
    public function show(SellerVerification $verification): View
    {
        $this->authorize('admin');

        return view('admin.seller-verification.show', [
            'verification' => $verification,
        ]);
    }

    /**
     * Approve verification
     */
    public function approve(Request $request, SellerVerification $verification)
    {
        $this->authorize('admin');

        if ($verification->status !== 'pending') {
            return back()->with('error', 'Only pending verifications can be approved.');
        }

        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        // Verify all documents are verified
        if (!$verification->allDocumentsVerified()) {
            return back()->with('error', 'All documents must be verified before approving the seller.');
        }

        $verification->approve(auth()->user(), $request->notes);

        // Send notification to seller
        $notificationService = new SellerVerificationNotificationService();
        $notificationService->notifyApproved($verification, $request->notes);

        return back()->with('success', 'Seller verification approved successfully and seller has been notified.');
    }

    /**
     * Reject verification
     */
    public function reject(Request $request, SellerVerification $verification)
    {
        $this->authorize('admin');

        if ($verification->status !== 'pending') {
            return back()->with('error', 'Only pending verifications can be rejected.');
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $verification->reject(auth()->user(), $request->reason);

        // Mark all documents as rejected if not already
        $verification->documents()->update(['verification_status' => 'rejected']);

        // Send notification to seller
        $notificationService = new SellerVerificationNotificationService();
        $notificationService->notifyRejected($verification, $request->reason);

        return back()->with('success', 'Seller verification rejected successfully and seller has been notified.');
    }

    /**
     * Verify individual document
     */
    public function verifyDocument(Request $request, SellerVerification $verification, SellerVerificationDocument $document)
    {
        $this->authorize('admin');

        $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $document->verify($request->note);

        return back()->with('success', 'Document verified successfully.');
    }

    /**
     * Reject individual document
     */
    public function rejectDocument(Request $request, SellerVerification $verification, SellerVerificationDocument $document)
    {
        $this->authorize('admin');

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $document->reject($request->reason);

        // Reset verification status to pending for re-submission
        $verification->update(['status' => 'pending']);
        $verification->user?->syncSellerStateFromApprovedVerifications();

        return back()->with('success', 'Document rejected. User will be notified to re-submit.');
    }

    /**
     * Revoke approved verification
     */
    public function revoke(Request $request, SellerVerification $verification)
    {
        $this->authorize('admin');

        if ($verification->status !== 'approved') {
            return back()->with('error', 'Only approved verifications can be revoked.');
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $verification->update([
            'status' => 'rejected',
            'verified_at' => null,
            'admin_notes' => 'Revoked: ' . $request->reason,
        ]);
        $verification->user?->syncSellerStateFromApprovedVerifications();

        return back()->with('success', 'Verification revoked successfully.');
    }

    /**
     * View document details
     */
    public function viewDocument(SellerVerification $verification, SellerVerificationDocument $document)
    {
        $this->authorize('admin');

        if ($document->seller_verification_id !== $verification->id) {
            abort(404);
        }

        return view('admin.seller-verification.view-document', [
            'verification' => $verification,
            'document' => $document,
        ]);
    }

    /**
     * Download document
     */
    public function downloadDocument(SellerVerification $verification, SellerVerificationDocument $document)
    {
        $this->authorize('admin');

        if ($document->seller_verification_id !== $verification->id) {
            abort(404);
        }

        return response()->download(
            storage_path('app/public/' . $document->document_path)
        );
    }

    /**
     * Export verification data (CSV/Excel)
     */
    public function export(Request $request)
    {
        $this->authorize('admin');

        $query = SellerVerification::with('user', 'category');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $verifications = $query->get();

        // CSV export implementation
        $filename = 'seller-verifications-' . now()->format('Y-m-d-H-i-s') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($verifications) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['User', 'Email', 'Category', 'Status', 'Verified At', 'Requested At']);

            foreach ($verifications as $verification) {
                fputcsv($file, [
                    $verification->user->name,
                    $verification->user->email,
                    $verification->category->name,
                    strtoupper($verification->status),
                    $verification->verified_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $verification->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Statistics dashboard
     */
    public function statistics(): View
    {
        $this->authorize('admin');

        $totalVerifications = SellerVerification::count();
        $pendingCount = SellerVerification::where('status', 'pending')->count();
        $approvedCount = SellerVerification::where('status', 'approved')->count();
        $rejectedCount = SellerVerification::where('status', 'rejected')->count();

        // Calculate average processing time - database agnostic
        $datetimeDiff = match(DB::connection()->getDriverName()) {
            'sqlite' => 'CAST((julianday(verified_at) - julianday(created_at)) AS INTEGER)',
            default => 'DATEDIFF(verified_at, created_at)',
        };

        $averageProcessingTime = SellerVerification::where('status', 'approved')
            ->selectRaw("AVG($datetimeDiff) as avg_days")
            ->value('avg_days') ?? 0;

        $recentApprovals = SellerVerification::where('status', 'approved')
            ->with('user', 'category')
            ->latest('verified_at')
            ->limit(10)
            ->get();

        return view('admin.seller-verification.statistics', [
            'totalVerifications' => $totalVerifications,
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
            'averageProcessingTime' => $averageProcessingTime,
            'recentApprovals' => $recentApprovals,
        ]);
    }
}
