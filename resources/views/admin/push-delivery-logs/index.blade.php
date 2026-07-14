@extends('admin.layout')

@section('title', 'FCM Delivery Logs')

@section('content')
    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <form method="GET" action="{{ route('admin.push-delivery-logs.index') }}" class="grid gap-3 md:grid-cols-6">
            <div>
                <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Status</label>
                <select id="status" name="status" class="app-select">
                    <option value="">All</option>
                    <option value="success" @selected(request('status') === 'success')>Success</option>
                    <option value="failure" @selected(request('status') === 'failure')>Failure</option>
                </select>
            </div>

            <div>
                <label for="user_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">User</label>
                <select id="user_id" name="user_id" class="app-select">
                    <option value="">All users</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>
                            {{ $user->name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="date_from" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">From date</label>
                <input id="date_from" type="date" name="date_from" value="{{ request('date_from') }}" class="app-input">
            </div>

            <div>
                <label for="date_to" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">To date</label>
                <input id="date_to" type="date" name="date_to" value="{{ request('date_to') }}" class="app-input">
            </div>

            <div>
                <label for="error_code" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-slate-500">Error code</label>
                <input id="error_code" type="text" name="error_code" value="{{ request('error_code') }}" class="app-input" placeholder="UNREGISTERED">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="app-btn-primary">Apply</button>
                <a href="{{ route('admin.push-delivery-logs.index') }}" class="app-btn-muted">Reset</a>
            </div>
        </form>
    </section>

    <section class="grid gap-4 sm:grid-cols-3">
        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Total FCM Attempts</p>
            <p class="mt-2 font-display text-3xl font-bold text-slate-900">{{ number_format($summary['total']) }}</p>
        </article>

        <article class="rounded-3xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-emerald-700">Successful</p>
            <p class="mt-2 font-display text-3xl font-bold text-emerald-700">{{ number_format($summary['success']) }}</p>
        </article>

        <article class="rounded-3xl border border-rose-200 bg-rose-50 p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-rose-700">Failed</p>
            <p class="mt-2 font-display text-3xl font-bold text-rose-700">{{ number_format($summary['failure']) }}</p>
        </article>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="font-display text-xl font-bold text-slate-900">Delivery Attempts</h2>
                <p class="text-sm text-slate-500">Use these logs to troubleshoot token quality, credential issues, and provider responses.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2">Timestamp</th>
                        <th class="px-3 py-2">User</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">HTTP</th>
                        <th class="px-3 py-2">Error code</th>
                        <th class="px-3 py-2">Target</th>
                        <th class="px-3 py-2">Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="px-3 py-3 whitespace-nowrap text-xs text-slate-600">
                                <p class="font-semibold text-slate-800">{{ $log->created_at?->format('Y-m-d H:i:s') }}</p>
                                <p>{{ $log->created_at?->diffForHumans() }}</p>
                            </td>
                            <td class="px-3 py-3">
                                @if ($log->user)
                                    <p class="font-semibold text-slate-900">{{ $log->user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $log->user->email }}</p>
                                @else
                                    <p class="text-xs font-semibold text-slate-500">Unknown user</p>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $log->status === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                    {{ strtoupper($log->status) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-slate-700">{{ $log->response_status ?? '—' }}</td>
                            <td class="px-3 py-3">
                                <p class="font-semibold text-slate-800">{{ $log->error_code ?: '—' }}</p>
                                <p class="mt-1 max-w-xs text-xs text-slate-500">{{ $log->error_message ?: 'No error message.' }}</p>
                            </td>
                            <td class="px-3 py-3 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit((string) $log->target, 44) ?: '—' }}</td>
                            <td class="px-3 py-3">
                                <details class="rounded-xl border border-slate-200 bg-slate-50 p-2">
                                    <summary class="cursor-pointer text-xs font-semibold text-slate-700">Inspect payload / response</summary>
                                    <div class="mt-2 space-y-2">
                                        <div>
                                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Payload</p>
                                            <pre class="mt-1 max-h-40 overflow-auto rounded-lg bg-slate-900/95 p-2 text-[11px] text-slate-100">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                        <div>
                                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Response</p>
                                            <pre class="mt-1 max-h-40 overflow-auto rounded-lg bg-slate-900/95 p-2 text-[11px] text-slate-100">{{ json_encode($log->response_body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </div>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-slate-600">No FCM delivery logs found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </section>
@endsection
