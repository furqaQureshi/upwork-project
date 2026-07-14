@extends('admin.layout')

@section('title', 'View Document - ' . ucfirst(str_replace('_', ' ', $document->document_type)))

@section('content')
    <section class="space-y-4">
<!-- Header -->
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                <a href="{{ route('admin.seller-verification.show', $verification) }}" class="app-btn-secondary text-sm mb-3 inline-block">← Back</a>
                <h2 class="font-display text-2xl font-bold text-slate-900">{{ ucfirst(str_replace('_', ' ', $document->document_type)) }}</h2>
                <p class="text-sm text-slate-600">Verification #{{ $verification->id }} • {{ $verification->user->name }}</p>
            </div>
            <div class="text-right">
                @if($document->isVerified())
                    <span class="inline-flex rounded-lg bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">✓ Verified</span>
                @elseif($document->verification_status === 'rejected')
                    <span class="inline-flex rounded-lg bg-rose-100 px-3 py-1 text-sm font-semibold text-rose-700">✗ Rejected</span>
                @else
                    <span class="inline-flex rounded-lg bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">Pending</span>
                @endif
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <!-- Document Viewer -->
            <div class="lg:col-span-2">
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 flex items-center justify-between">
                        <h3 class="font-display font-bold text-slate-900">Document Preview</h3>
                        <a href="{{ route('admin.seller-verification.download-document', [$verification, $document]) }}" class="app-btn-secondary text-sm">
                            ⬇ Download
                        </a>
                    </div>
                    <div class="p-5 bg-slate-900 flex items-center justify-center" style="min-height: 500px;">
                        @php
                            $ext = strtolower(pathinfo($document->file_path ?? $document->document_path, PATHINFO_EXTENSION));
                            $imageMimes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                        @endphp

                        @if(in_array($ext, $imageMimes))
                            <img src="{{ $document->file_url ?? $document->getDocumentUrl() }}" alt="{{ $document->document_type }}" class="max-h-full max-w-full">
                        @elseif($ext === 'pdf')
                            <iframe src="{{ $document->file_url ?? $document->getDocumentUrl() }}" class="w-full h-full" style="min-height: 500px;"></iframe>
                        @else
                            <div class="text-center">
                                <p class="text-slate-400 mb-3">Preview not available for this file type</p>
                                <a href="{{ route('admin.seller-verification.download-document', [$verification, $document]) }}" class="app-btn-primary">
                                    Download to View
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-4">
                <!-- Document Info -->
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-display font-bold text-slate-900 mb-4">Document Information</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Type</p>
                            <p class="font-semibold text-slate-900">{{ ucfirst(str_replace('_', ' ', $document->document_type)) }}</p>
                        </div>
                        @if($document->document_number)
                            <div>
                                <p class="text-xs text-slate-600 uppercase tracking-wide">Document Number</p>
                                <p class="font-mono text-sm text-slate-900">{{ $document->document_number }}</p>
                            </div>
                        @endif
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Uploaded</p>
                            <p class="font-semibold text-slate-900">{{ $document->created_at->format('M d, Y H:i') }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">File Type</p>
                            <p class="font-semibold text-slate-900 uppercase">{{ $ext }}</p>
                        </div>
                    </div>
                </div>

                <!-- Status & Actions -->
                @if(!$document->isVerified())
                    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                        <h3 class="font-display font-bold text-amber-900 mb-3">Review</h3>
                        <div class="space-y-2">
                            <button onclick="document.getElementById('verifyForm').submit()" class="app-btn-primary w-full text-sm">
                                ✓ Verify Document
                            </button>
                            <button onclick="document.getElementById('rejectForm').submit()" class="app-btn-muted w-full text-sm">
                                ✗ Reject Document
                            </button>
                        </div>
                    </div>
                @else
                    <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm text-center">
                        <p class="text-2xl text-emerald-600 mb-2">✓</p>
                        <p class="font-semibold text-emerald-900 mb-2">Verified</p>
                        <p class="text-xs text-emerald-700">{{ $document->updated_at->format('M d, Y H:i') }}</p>
                    </div>
                @endif

                <!-- Notes -->
                @if($document->verification_note)
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="font-display font-bold text-slate-900 mb-3">Review Note</h3>
                        <p class="text-sm text-slate-700">{{ $document->verification_note }}</p>
                    </div>
                @endif

                <!-- Seller Information -->
                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-display font-bold text-slate-900 mb-3">Seller Information</h3>
                    <div class="space-y-2 text-sm">
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Name</p>
                            <p class="font-semibold text-slate-900">{{ $verification->user->name }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-600 uppercase tracking-wide">Email</p>
                            <p class="font-semibold text-slate-900">{{ $verification->user->email }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Form -->
        <form id="verifyForm" action="{{ route('admin.seller-verification.verify-document', [$verification, $document]) }}" method="POST" class="hidden">
            @csrf
        </form>

        <!-- Rejection Form -->
        <form id="rejectForm" action="{{ route('admin.seller-verification.reject-document', [$verification, $document]) }}" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="reason" value="">
        </form>
    </section>

    <script>
        document.getElementById('verifyForm')?.addEventListener('submit', function(e) {
            if (!confirm('Verify this document?')) {
                e.preventDefault();
            }
        });

        document.getElementById('rejectForm')?.addEventListener('submit', function(e) {
            const reason = prompt('Enter rejection reason (required):');
            if (!reason) {
                e.preventDefault();
                return;
            }
            this.querySelector('input[name="reason"]').value = reason;
        });
    </script>
@endsection

