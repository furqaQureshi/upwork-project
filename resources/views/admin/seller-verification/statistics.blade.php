@extends('admin.layout')

@section('title', 'Verification Statistics')

@section('content')
    <section class="space-y-4">
        <!-- Header -->
        <div class="rounded-3xl border border-slate-200 bg-white p-5">
            <h2 class="font-display text-2xl font-bold text-slate-900">Verification Statistics</h2>
            <p class="text-sm text-slate-600">Overview of seller verification metrics and performance</p>
        </div>

        <!-- KPI Cards -->
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Verifications</p>
                <p class="mt-1 text-3xl font-bold text-blue-600">{{ $totalVerifications }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Pending Review</p>
                <p class="mt-1 text-3xl font-bold text-amber-600">{{ $pendingCount }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Approved</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ $approvedCount }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Rejected</p>
                <p class="mt-1 text-3xl font-bold text-rose-600">{{ $rejectedCount }}</p>
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="grid gap-4 md:grid-cols-2">
            <!-- Performance -->
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-display text-lg font-bold text-slate-900 mb-4">Performance Metrics</h3>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-semibold text-slate-700">Approval Rate</span>
                            <span class="text-sm font-bold text-emerald-600">{{ number_format(($approvedCount / max($totalVerifications, 1)) * 100, 1) }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ ($approvedCount / max($totalVerifications, 1)) * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-semibold text-slate-700">Pending Rate</span>
                            <span class="text-sm font-bold text-amber-600">{{ number_format(($pendingCount / max($totalVerifications, 1)) * 100, 1) }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-amber-500" style="width: {{ ($pendingCount / max($totalVerifications, 1)) * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-semibold text-slate-700">Rejection Rate</span>
                            <span class="text-sm font-bold text-rose-600">{{ number_format(($rejectedCount / max($totalVerifications, 1)) * 100, 1) }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-rose-500" style="width: {{ ($rejectedCount / max($totalVerifications, 1)) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Processing Efficiency -->
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="font-display text-lg font-bold text-slate-900 mb-4">Processing Efficiency</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="text-center">
                        <p class="text-3xl font-bold text-blue-600">{{ number_format($averageProcessingTime, 1) }}</p>
                        <p class="text-xs text-slate-600 mt-1">Average Days to Approve</p>
                    </div>
                    <div class="text-center">
                        <p class="text-3xl font-bold text-emerald-600">{{ $approvedCount > 0 ? round($approvedCount / max($totalVerifications, 1) * 100, 1) : 0 }}%</p>
                        <p class="text-xs text-slate-600 mt-1">Approval Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Approvals Table -->
        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50 px-5 py-3 flex justify-between items-center">
                <h3 class="font-display font-bold text-slate-900">Recent Approvals</h3>
                <a href="{{ route('admin.seller-verification.index', ['status' => 'approved']) }}" class="app-btn-secondary text-xs">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3">Seller</th>
                            <th class="px-5 py-3">Package</th>
                            <th class="px-5 py-3">Verified By</th>
                            <th class="px-5 py-3">Verified Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentApprovals as $approval)
                            <tr class="border-b border-slate-100 hover:bg-slate-50">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-slate-900">{{ $approval->user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $approval->user->email }}</p>
                                </td>
                                <td class="px-5 py-3 text-sm">{{ $approval->subscriptionPackage?->name }}</td>
                                <td class="px-5 py-3 text-sm">{{ $approval->verifiedByAdmin?->name ?? 'N/A' }}</td>
                                <td class="px-5 py-3 text-sm">{{ $approval->verified_at->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-sm text-slate-500">No approvals yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary -->
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <h3 class="font-display font-bold text-slate-900 mb-4">Summary</h3>
            <div class="grid gap-4 md:grid-cols-4">
                <div>
                    <p class="text-xs text-slate-600 uppercase tracking-wide">Total Processed</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1">{{ $approvedCount + $rejectedCount }}/{{ $totalVerifications }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-600 uppercase tracking-wide">Success Rate</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1">{{ $totalVerifications > 0 ? number_format(($approvedCount / ($approvedCount + $rejectedCount)) * 100, 1) : 0 }}%</p>
                </div>
                <div>
                    <p class="text-xs text-slate-600 uppercase tracking-wide">Pending Review</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1">{{ $pendingCount }}</p>
                </div>
                <div>
                    <p class="text-xs text-slate-600 uppercase tracking-wide">Avg Processing</p>
                    <p class="text-2xl font-bold text-slate-900 mt-1">{{ number_format($averageProcessingTime, 1) }} days</p>
                </div>
            </div>
        </div>
    </section>
@endsection

