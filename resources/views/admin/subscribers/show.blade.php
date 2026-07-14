@extends('admin.layout')

@section('title', 'Subscriber Details')

@section('content')
<div class="container-fluid py-4">
    <!-- Back Button -->
    <div class="mb-4">
        <a href="{{ route('admin.subscribers.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Subscribers
        </a>
    </div>

    <div class="row">
        <!-- Subscriber Info -->
        <div class="col-lg-8">
            <!-- User Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user"></i> Subscriber Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Name</h6>
                            <p class="mb-0"><strong>{{ $subscriber->user?->name ?? 'N/A' }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Email</h6>
                            <p class="mb-0"><strong>{{ $subscriber->user?->email ?? 'N/A' }}</strong></p>
                        </div>
                        <div class="col-md-6 mt-3">
                            <h6 class="text-muted mb-2">Phone</h6>
                            <p class="mb-0"><strong>{{ $subscriber->user?->phone ?? 'N/A' }}</strong></p>
                        </div>
                        <div class="col-md-6 mt-3">
                            <h6 class="text-muted mb-2">Member Since</h6>
                            <p class="mb-0"><strong>{{ $subscriber->user?->created_at->format('M d, Y') ?? 'N/A' }}</strong></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscription Details -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-box"></i> Subscription Details
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $isExpiringSoon = $subscriber->expires_at && $subscriber->expires_at->diffInDays(now()) <= 7;
                        $isExpired = $subscriber->expires_at && $subscriber->expires_at->isPast();
                        $daysRemaining = $subscriber->expires_at ? $subscriber->expires_at->diffInDays(now()) : null;
                    @endphp
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Package Name</h6>
                            <p class="mb-0"><strong>{{ $subscriber->package?->name ?? 'N/A' }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Package Type</h6>
                            <p class="mb-0">
                                <span class="badge bg-secondary">{{ $subscriber->package?->package_type ?? 'N/A' }}</span>
                            </p>
                        </div>
                        <div class="col-md-6 mt-3">
                            <h6 class="text-muted mb-2">Status</h6>
                            <p class="mb-0">
                                <span class="badge bg-{{ $isExpired ? 'danger' : ($isExpiringSoon ? 'warning' : 'success') }}">
                                    {{ $isExpired ? 'Expired' : ($isExpiringSoon ? 'Expiring Soon' : 'Active') }}
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6 mt-3">
                            <h6 class="text-muted mb-2">Purchase Date</h6>
                            <p class="mb-0"><strong>{{ $subscriber->created_at->format('M d, Y h:i A') }}</strong></p>
                        </div>
                        <div class="col-md-6 mt-3">
                            <h6 class="text-muted mb-2">Expiry Date</h6>
                            <p class="mb-0">
                                <strong>
                                    @if($subscriber->expires_at)
                                        {{ $subscriber->expires_at->format('M d, Y') }}
                                        @if($isExpiringSoon && !$isExpired)
                                            <span class="text-warning">({{ $daysRemaining }} days left)</span>
                                        @endif
                                    @else
                                        No expiry (Lifetime)
                                    @endif
                                </strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-credit-card"></i> Payment Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Amount Paid</h6>
                            <p class="mb-0"><strong>₹{{ number_format($subscriber->amount ?? 0, 2) }}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Payment Method</h6>
                            <p class="mb-0"><strong>{{ $subscriber->payment_method ?? 'N/A' }}</strong></p>
                        </div>
                        <div class="col-md-6 mt-3">
                            <h6 class="text-muted mb-2">Transaction ID</h6>
                            <p class="mb-0"><code>{{ $subscriber->transaction_id ?? 'N/A' }}</code></p>
                        </div>
                        <div class="col-md-6 mt-3">
                            <h6 class="text-muted mb-2">Payment Status</h6>
                            <p class="mb-0">
                                <span class="badge bg-success">{{ $subscriber->payment_status ?? 'Paid' }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Package Features -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-star"></i> Package Features
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Item Limit</h6>
                            <p class="mb-0">
                                <strong>
                                    @if($subscriber->package?->item_limit_type === 'unlimited')
                                        Unlimited
                                    @else
                                        {{ $subscriber->package?->item_limit_count ?? 'N/A' }} Items
                                    @endif
                                </strong>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-2">Listing Duration</h6>
                            <p class="mb-0">
                                <strong>
                                    @if($subscriber->package?->listing_duration_type === 'unlimited')
                                        Unlimited
                                    @else
                                        {{ $subscriber->package?->listing_duration_days ?? 'N/A' }} Days
                                    @endif
                                </strong>
                            </p>
                        </div>
                        <div class="col-md-6 mt-3">
                            <h6 class="text-muted mb-2">Calls Allowed</h6>
                            <p class="mb-0">
                                @if($subscriber->package?->allows_call)
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Yes</span>
                                @else
                                    <span class="badge bg-secondary"><i class="fas fa-times"></i> No</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6 mt-3">
                            <h6 class="text-muted mb-2">AI Features</h6>
                            <p class="mb-0">
                                @if($subscriber->package?->allows_ai)
                                    <span class="badge bg-success"><i class="fas fa-check"></i> Enabled</span>
                                @else
                                    <span class="badge bg-secondary"><i class="fas fa-times"></i> Disabled</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('admin.users.edit', $subscriber->user) }}" class="btn btn-secondary btn-sm">
                        <i class="fas fa-user-edit"></i> Edit User Profile
                    </a>
                    <a href="{{ route('admin.subscribers.renew', $subscriber) }}" class="btn btn-info btn-sm">
                        <i class="fas fa-sync"></i> Renew Subscription
                    </a>
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#upgradeModal">
                        <i class="fas fa-arrow-up"></i> Upgrade Package
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        <i class="fas fa-ban"></i> Cancel Subscription
                    </button>
                </div>
            </div>

            <!-- Activity Summary -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Activity</h5>
                </div>
                <div class="card-body small">
                    <div class="mb-3">
                        <h6 class="text-muted mb-1">Total Listings</h6>
                        <p class="mb-0"><strong>{{ $subscriber->user?->listings_count ?? 0 }}</strong></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted mb-1">Active Listings</h6>
                        <p class="mb-0"><strong>{{ $subscriber->user?->active_listings_count ?? 0 }}</strong></p>
                    </div>
                    <div class="mb-0">
                        <h6 class="text-muted mb-1">Last Login</h6>
                        <p class="mb-0">
                            <strong>{{ $subscriber->user?->last_login_at ? $subscriber->user->last_login_at->format('M d, Y') : 'Never' }}</strong>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Subscription History -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Subscription History</h5>
                </div>
                <div class="card-body small">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <p class="mb-1"><strong>Current Subscription</strong></p>
                                <p class="text-muted mb-0">{{ $subscriber->created_at->format('M d, Y') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upgrade Modal -->
<div class="modal fade" id="upgradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">Upgrade Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Select a new package to upgrade:</p>
                <div class="list-group">
                    @forelse($availablePackages as $pkg)
                        <button type="button" class="list-group-item list-group-item-action" data-bs-dismiss="modal"
                                onclick="upgradePackage('{{ route('admin.subscribers.upgrade', [$subscriber, $pkg]) }}')">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $pkg->name }}</strong>
                                <span class="badge bg-primary">₹{{ number_format($pkg->final_price, 2) }}</span>
                            </div>
                            <small class="text-muted">{{ $pkg->package_type }}</small>
                        </button>
                    @empty
                        <p class="text-muted">No other packages available</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancel Subscription</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel this subscription?</p>
                <p class="text-muted small">This action will immediately deactivate the subscription for <strong>{{ $subscriber->user?->name }}</strong>.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep Active</button>
                <form action="{{ route('admin.subscribers.cancel', $subscriber) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban"></i> Cancel Subscription
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function upgradePackage(url) {
    if (confirm('Upgrade to this package?')) {
        window.location.href = url;
    }
}
</script>
@endsection
