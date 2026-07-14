@extends('admin.layout')

@section('title', 'Subscriber Users')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">
                        <i class="fas fa-users"></i> Subscriber Users
                    </h1>
                    <p class="text-muted">Manage all active and inactive subscription users</p>
                </div>
                <div>
                    <a href="{{ route('admin.subscribers.export') }}" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-download"></i> Export
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Subscribers</h6>
                    <h3 class="mb-0">{{ $totalSubscribers }}</h3>
                    <small class="text-success"><i class="fas fa-arrow-up"></i> {{ $newThisMonth }} this month</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Active Subscriptions</h6>
                    <h3 class="mb-0">{{ $activeSubscriptions }}</h3>
                    <small class="text-muted">Currently valid</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Expiring Soon</h6>
                    <h3 class="mb-0">{{ $expiringSoon }}</h3>
                    <small class="text-warning">Within 7 days</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Total Revenue</h6>
                    <h3 class="mb-0">₹{{ number_format($totalRevenue, 2) }}</h3>
                    <small class="text-muted">All time</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.subscribers.index') }}" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search by name, email, phone..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Status</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                        <option value="expiring_soon" {{ request('status') === 'expiring_soon' ? 'selected' : '' }}>Expiring Soon</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="package_type" class="form-select form-select-sm">
                        <option value="">All Packages</option>
                        <option value="listing" {{ request('package_type') === 'listing' ? 'selected' : '' }}>Listing</option>
                        <option value="featured" {{ request('package_type') === 'featured' ? 'selected' : '' }}>Featured</option>
                        <option value="seller_verification" {{ request('package_type') === 'seller_verification' ? 'selected' : '' }}>Seller Verification</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Subscribers Table -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">Active Subscribers</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Package</th>
                        <th>Status</th>
                        <th>Purchase Date</th>
                        <th>Expiry Date</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscribers as $subscriber)
                        @php
                            $isExpiringSoon = $subscriber->expires_at && $subscriber->expires_at->diffInDays(now()) <= 7;
                            $isExpired = $subscriber->expires_at && $subscriber->expires_at->isPast();
                            $statusClass = $isExpired ? 'danger' : ($isExpiringSoon ? 'warning' : 'success');
                            $statusText = $isExpired ? 'Expired' : ($isExpiringSoon ? 'Expiring Soon' : 'Active');
                        @endphp
                        <tr>
                            <td>
                                <div>
                                    <strong>{{ $subscriber->user?->name ?? 'N/A' }}</strong>
                                    @if($subscriber->user?->phone)
                                        <br><small class="text-muted">{{ $subscriber->user->phone }}</small>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <small>{{ $subscriber->user?->email ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <div>
                                    <strong>{{ $subscriber->package?->name ?? 'N/A' }}</strong>
                                    <br>
                                    <span class="badge bg-secondary">{{ $subscriber->package?->package_type ?? 'N/A' }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $statusClass }}">{{ $statusText }}</span>
                            </td>
                            <td>
                                <small>{{ $subscriber->created_at->format('M d, Y') }}</small>
                            </td>
                            <td>
                                @if($subscriber->expires_at)
                                    <small>{{ $subscriber->expires_at->format('M d, Y') }}</small>
                                    @if($isExpiringSoon && !$isExpired)
                                        <br><strong class="text-warning">{{ $subscriber->expires_at->diffInDays(now()) }} days left</strong>
                                    @endif
                                @else
                                    <small class="text-muted">No expiry</small>
                                @endif
                            </td>
                            <td>
                                <strong>₹{{ number_format($subscriber->amount ?? 0, 2) }}</strong>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end text-sm">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.subscribers.show', $subscriber) }}">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.users.edit', $subscriber->user) }}">
                                                <i class="fas fa-user-edit"></i> Edit User
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.subscribers.renew', $subscriber) }}">
                                                <i class="fas fa-sync"></i> Renew Subscription
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" data-bs-toggle="modal" 
                                               data-bs-target="#cancelModal{{ $subscriber->id }}">
                                                <i class="fas fa-ban"></i> Cancel Subscription
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2"></i>
                                <p>No subscribers found</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($subscribers->hasPages())
    <div class="d-flex justify-content-center mt-4">
        {{ $subscribers->links() }}
    </div>
    @endif
</div>

<!-- Cancel Subscription Modals -->
@foreach($subscribers as $subscriber)
<div class="modal fade" id="cancelModal{{ $subscriber->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Cancel Subscription</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel the subscription for <strong>{{ $subscriber->user?->name }}</strong>?</p>
                <p class="text-muted small">This action will immediately deactivate their subscription.</p>
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
@endforeach
@endsection
