@extends('layouts.app')

@section('title', 'Verification #{{ $verification->id }}')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 py-8">
    <div class="container mx-auto px-4 max-w-6xl">

        {{-- Back Link --}}
        <a href="{{ route('seller-verification.index') }}"
           class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800 mb-6 transition">
            ← Back to My Verifications
        </a>

        <div class="grid gap-8 lg:grid-cols-3">

            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Status Header --}}
                @php
                    $isApproved = $verification->isApproved();
                    $isRejected = $verification->isRejected();
                    $headerBg = $isApproved ? 'bg-emerald-600' : ($isRejected ? 'bg-red-600' : 'bg-amber-500');
                    $badgeText = $isApproved ? '✓ APPROVED' : ($isRejected ? '✗ REJECTED' : '⏳ PENDING');
                @endphp
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="{{ $headerBg }} px-7 py-5 flex items-center justify-between">
                        <h2 class="text-xl font-bold text-white">Verification #{{ $verification->id }}</h2>
                        <span class="rounded-full bg-white/90 px-4 py-1 text-sm font-bold
                            {{ $isApproved ? 'text-emerald-700' : ($isRejected ? 'text-red-700' : 'text-amber-700') }}">
                            {{ $badgeText }}
                        </span>
                    </div>

                    <div class="p-6 grid sm:grid-cols-2 gap-6">
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Category</p>
                            <p class="font-semibold text-slate-800 mt-1">{{ $verification->category->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Package</p>
                            <p class="font-semibold text-slate-800 mt-1">{{ $verification->subscriptionPackage?->name }}</p>
                            <p class="text-xs text-slate-400">₹{{ number_format($verification->subscriptionPackage?->final_price ?? 0, 2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Submitted</p>
                            <p class="font-semibold text-slate-800 mt-1">{{ $verification->created_at->format('M d, Y') }}</p>
                            <p class="text-xs text-slate-400">{{ $verification->created_at->format('h:i A') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wide">Last Updated</p>
                            <p class="font-semibold text-slate-800 mt-1">{{ $verification->updated_at->format('M d, Y') }}</p>
                            <p class="text-xs text-slate-400">{{ $verification->updated_at->diffForHumans() }}</p>
                        </div>

                        @if($verification->verified_at)
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wide">Verified Date</p>
                                <p class="font-semibold text-slate-800 mt-1">{{ $verification->verified_at->format('M d, Y') }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500 uppercase tracking-wide">Status</p>
                                @php
                                    $expiry = $verification->verified_at->add(
                                        $verification->subscriptionPackage?->package_duration_type === 'unlimited'
                                            ? 'P1Y' : 'P' . $verification->subscriptionPackage?->package_duration_days . 'D'
                                    );
                                @endphp
                                <span class="mt-1 inline-block rounded-full px-3 py-0.5 text-xs font-semibold
                                    {{ $expiry < now() ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}">
                                    {{ $expiry < now() ? 'Expired' : 'Active' }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Admin Notes --}}
                @if($verification->admin_notes)
                    @php
                        $noteStyle = $isApproved ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
                            : ($isRejected ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800');
                    @endphp
                    <div class="rounded-2xl border {{ $noteStyle }} p-4">
                        <p class="text-sm font-semibold mb-1">💬 Admin Notes</p>
                        <p class="text-sm">{{ $verification->admin_notes }}</p>
                        @if($verification->verifiedByAdmin)
                            <p class="text-xs mt-2 opacity-70">By: {{ $verification->verifiedByAdmin->name }}</p>
                        @endif
                    </div>
                @endif

                {{-- Documents Table --}}
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50 px-6 py-4">
                        <h3 class="font-bold text-slate-800">📄 Submitted Documents</h3>
                    </div>

                    @if($verification->documents->isEmpty())
                        <div class="px-6 py-10 text-center text-sm text-slate-400">No documents submitted yet.</div>
                    @else
                        <div class="divide-y divide-slate-50">
                            @foreach($verification->documents as $doc)
                                @php
                                    $docApproved  = $doc->isVerified();
                                    $docRejected  = $doc->verification_status === 'rejected';
                                    $docStatusBg  = $docApproved ? 'bg-emerald-100 text-emerald-800'
                                        : ($docRejected ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800');
                                    $docStatusTxt = $docApproved ? '✓ Verified' : ($docRejected ? '✗ Rejected' : '⏳ Pending');
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 px-6 py-4">
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-slate-800">
                                            {{ ucfirst(str_replace('_', ' ', $doc->document_type)) }}
                                        </p>
                                        @if($doc->document_number)
                                            <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded text-slate-600">{{ $doc->document_number }}</code>
                                        @endif
                                        @if($doc->verification_note)
                                            <p class="text-xs text-slate-500 mt-1">{{ Str::limit($doc->verification_note, 60) }}</p>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="rounded-full px-3 py-0.5 text-xs font-semibold {{ $docStatusBg }}">{{ $docStatusTxt }}</span>
                                        <a href="{{ $doc->getDocumentUrl() }}" target="_blank"
                                           class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 transition">
                                            View
                                        </a>
                                        @if($docRejected)
                                            <button type="button"
                                                    onclick="document.getElementById('reupload{{ $doc->id }}').classList.toggle('hidden')"
                                                    class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-100 transition">
                                                Re-upload
                                            </button>
                                        @endif
                                    </div>
                                </div>

                                {{-- Re-upload panel --}}
                                @if($docRejected)
                                    <div id="reupload{{ $doc->id }}" class="hidden border-t border-slate-100 bg-amber-50 px-6 py-4">
                                        <p class="text-sm font-semibold text-amber-800 mb-2">Rejection Reason:</p>
                                        <p class="text-sm text-amber-700 mb-4">{{ $doc->verification_note }}</p>
                                        <form action="{{ route('seller-verification.update-document', [$verification, $doc]) }}"
                                              method="POST" enctype="multipart/form-data">
                                            @csrf
                                            <div class="flex gap-3 items-end">
                                                <div class="flex-1">
                                                    <label class="block text-xs font-semibold text-slate-700 mb-1">New File (PDF / JPG / PNG, max 5 MB)</label>
                                                    <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required
                                                           class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-emerald-700">
                                                </div>
                                                <div class="flex gap-2">
                                                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Upload</button>
                                                    <button type="button"
                                                            onclick="document.getElementById('reupload{{ $doc->id }}').classList.add('hidden')"
                                                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">Cancel</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex gap-3">
                    <a href="{{ route('seller-verification.index') }}"
                       class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 transition">
                        ← Back to List
                    </a>
                    @if($verification->isPending() || $verification->isRejected())
                        <button type="button"
                                onclick="document.getElementById('deleteModal').classList.remove('hidden')"
                                class="rounded-xl bg-red-50 border border-red-200 px-5 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-100 transition">
                            Delete Request
                        </button>
                    @endif
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">

                {{-- Timeline --}}
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                        <h3 class="font-bold text-slate-800 text-sm">🕐 Timeline</h3>
                    </div>
                    <div class="p-5 space-y-4">
                        {{-- Submitted --}}
                        <div class="flex items-start gap-3">
                            <div class="w-3 h-3 rounded-full bg-blue-500 shrink-0 mt-1"></div>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Submitted</p>
                                <p class="text-xs text-slate-400">{{ $verification->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>

                        @if($verification->documents->where('verification_status', '!=', 'pending')->count() > 0)
                            <div class="flex items-start gap-3">
                                <div class="w-3 h-3 rounded-full bg-cyan-500 shrink-0 mt-1"></div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">Documents Reviewed</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $verification->documents->where('verification_status', 'verified')->count() }} verified,
                                        {{ $verification->documents->where('verification_status', 'rejected')->count() }} rejected
                                    </p>
                                </div>
                            </div>
                        @endif

                        @if($isApproved)
                            <div class="flex items-start gap-3">
                                <div class="w-3 h-3 rounded-full bg-emerald-500 shrink-0 mt-1"></div>
                                <div>
                                    <p class="text-sm font-semibold text-emerald-700">Approved</p>
                                    <p class="text-xs text-slate-400">{{ $verification->verified_at->format('M d, Y h:i A') }}</p>
                                </div>
                            </div>
                        @elseif($isRejected)
                            <div class="flex items-start gap-3">
                                <div class="w-3 h-3 rounded-full bg-red-500 shrink-0 mt-1"></div>
                                <div>
                                    <p class="text-sm font-semibold text-red-700">Rejected</p>
                                    <p class="text-xs text-slate-400">{{ $verification->updated_at->format('M d, Y h:i A') }}</p>
                                </div>
                            </div>
                        @else
                            <div class="flex items-start gap-3">
                                <div class="w-3 h-3 rounded-full bg-amber-400 shrink-0 mt-1 animate-pulse"></div>
                                <div>
                                    <p class="text-sm font-semibold text-amber-700">Under Review</p>
                                    <p class="text-xs text-slate-400">Waiting for admin verification</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Status Info --}}
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                        <h3 class="font-bold text-slate-800 text-sm">📋 Status</h3>
                    </div>
                    <div class="p-5">
                        @if($isApproved)
                            <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">
                                ✓ Your seller account is verified. Display the verified badge with pride!
                            </div>
                        @elseif($isRejected)
                            <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                                ✗ Your verification was rejected. Please correct the issues and resubmit.
                            </div>
                        @else
                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                                ⏳ Your application is under review. You will be notified once a decision is made.
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
@if($verification->isPending() || $verification->isRejected())
    <div id="deleteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl p-7 max-w-sm w-full mx-4">
            <h3 class="text-lg font-bold text-slate-900 mb-2">Delete Verification Request?</h3>
            <p class="text-slate-500 text-sm mb-6">This action cannot be undone.</p>
            <div class="flex gap-3">
                <button onclick="document.getElementById('deleteModal').classList.add('hidden')"
                        class="flex-1 rounded-xl border border-slate-200 bg-slate-50 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                    Cancel
                </button>
                <form action="{{ route('seller-verification.destroy', $verification) }}" method="POST" class="flex-1">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="w-full rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
@endif
@endsection
