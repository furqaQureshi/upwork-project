@extends('admin.layout')

@section('title', 'Verify Seller #' . $verification->id)

@section('content')
    <section class="space-y-4">
        <!-- Header -->
        <div class="flex items-center justify-between gap-3">
            <div>
                <a href="{{ route('admin.seller-verification.index') }}" class="app-btn-secondary text-sm mb-3 inline-block">← Back</a>
                <h2 class="font-display text-2xl font-bold text-slate-900">Verification #{{ $verification->id }}</h2>
                <p class="text-sm text-slate-600">
                    @if($verification->isApproved())
                        <span class="inline-flex rounded-lg bg-emerald-100 px-2 py-1 text-sm font-semibold text-emerald-700">Approved</span>
                    @elseif($verification->isRejected())
                        <span class="inline-flex rounded-lg bg-rose-100 px-2 py-1 text-sm font-semibold text-rose-700">Rejected</span>
                    @else
                        <span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-sm font-semibold text-amber-700">Pending</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-4">
                <!-- Seller Information -->
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-display font-bold text-slate-900 mb-4">Seller Information</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Name</p>
                            <p class="font-semibold text-slate-900 mt-1">{{ $verification->user->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Email</p>
                            <p class="font-semibold text-slate-900 mt-1">{{ $verification->user->email }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Phone</p>
                            <p class="font-semibold text-slate-900 mt-1">{{ $verification->user->phone ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Location</p>
                            <p class="font-semibold text-slate-900 mt-1">{{ $verification->user->city }}, {{ $verification->user->state }}</p>
                        </div>
                    </div>
                </div>

                <!-- Verification Details -->
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-display font-bold text-slate-900 mb-4">Verification Details</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Category</p>
                            <p class="font-semibold text-slate-900 mt-1">{{ $verification->category?->name ?? 'All Categories' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Package</p>
                            <p class="font-semibold text-slate-900 mt-1">{{ $verification->subscriptionPackage?->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Seller Type</p>
                            <span class="inline-flex rounded-lg bg-slate-100 px-2 py-1 text-sm font-semibold text-slate-700 mt-1">{{ $verification->subscriptionPackage?->seller_tier_label ?? 'Seller Package' }}</span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Badge</p>
                            <span class="inline-flex rounded-lg bg-blue-100 px-2 py-1 text-sm font-semibold text-blue-700 mt-1">{{ $verification->subscriptionPackage?->resolved_seller_badge_label ?? 'VERIFIED SELLER' }}</span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Submitted</p>
                            <p class="font-semibold text-slate-900 mt-1">{{ $verification->created_at->format('M d, Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Last Updated</p>
                            <p class="font-semibold text-slate-900 mt-1">{{ $verification->updated_at->format('M d, Y H:i') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Documents -->
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-3">
                        <h3 class="font-display font-bold text-slate-900">Document Verification</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                    <th class="px-5 py-3">Document Type</th>
                                    <th class="px-5 py-3">Number</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($verification->documents as $doc)
                                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                                        <td class="px-5 py-3 font-semibold">{{ ucfirst(str_replace('_', ' ', $doc->document_type)) }}</td>
                                        <td class="px-5 py-3"><code class="text-xs">{{ $doc->document_number ?? '-' }}</code></td>
                                        <td class="px-5 py-3">
                                            @if($doc->isVerified())
                                                <span class="inline-flex rounded-lg bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Verified</span>
                                            @elseif($doc->verification_status === 'rejected')
                                                <span class="inline-flex rounded-lg bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-700">Rejected</span>
                                            @else
                                                <span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">Pending</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3">
                                            <a href="{{ route('admin.seller-verification.view-document', [$verification, $doc]) }}" target="_blank" class="app-btn-secondary text-xs">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <!-- Quick Actions -->
                @if($verification->isPending())
                    <div class="rounded-3xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                        <h3 class="font-display font-bold text-blue-900 mb-3">Quick Actions</h3>
                        @if($verification->allDocumentsVerified())
                            <button onclick="document.getElementById('approveForm').submit()" class="app-btn-primary w-full text-sm mb-2">Approve</button>
                        @else
                            <p class="text-xs text-blue-700 mb-3">Please verify all documents before approving</p>
                        @endif
                        <button onclick="document.getElementById('rejectForm').submit()" class="app-btn-muted w-full text-sm">Reject</button>
                    </div>
                @elseif($verification->isApproved())
                    <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm text-center">
                        <p class="text-3xl text-emerald-600 mb-2">✓</p>
                        <p class="font-semibold text-emerald-900 mb-3">Verified on {{ $verification->verified_at->format('M d, Y') }}</p>
                        <button onclick="document.getElementById('revokeForm').submit()" class="app-btn-muted w-full text-sm">Revoke</button>
                    </div>
                @endif

                <!-- Admin Notes -->
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-display font-bold text-slate-900 mb-3">Admin Notes</h3>
                    @if($verification->admin_notes)
                        <p class="text-sm text-slate-700 mb-2">{{ $verification->admin_notes }}</p>
                        @if($verification->verifiedByAdmin)
                            <p class="text-xs text-slate-500 mt-2">By {{ $verification->verifiedByAdmin->name }}, {{ $verification->verified_at->format('M d, Y') }}</p>
                        @endif
                    @else
                        <p class="text-sm text-slate-500">No notes added yet</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- Approve Form -->
    <form id="approveForm" action="{{ route('admin.seller-verification.approve', $verification) }}" method="POST" class="hidden">
        @csrf
        <textarea name="notes" placeholder="Approval notes..."></textarea>
    </form>

    <!-- Reject Form -->
    <form id="rejectForm" action="{{ route('admin.seller-verification.reject', $verification) }}" method="POST" class="hidden">
        @csrf
        <input type="text" name="reason" placeholder="Rejection reason..." required>
    </form>

    <!-- Revoke Form -->
    <form id="revokeForm" action="{{ route('admin.seller-verification.revoke', $verification) }}" method="POST" class="hidden">
        @csrf
        <input type="text" name="reason" placeholder="Revocation reason..." required>
    </form>
@endsection

