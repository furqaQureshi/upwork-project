@extends('admin.layout')

@section('title', 'Edit Subscription Package')

@section('content')
    <section class="mx-auto max-w-5xl space-y-5">
        <div>
            <h2 class="font-display text-2xl font-bold text-slate-900">Edit {{ $package->name }}</h2>
            <p class="text-sm text-slate-600">Update package pricing and settings.</p>
        </div>

        @include('admin.subscription-packages.form', [
            'package' => $package,
            'categories' => $categories,
            'action' => route('admin.subscription-packages.update', $package),
            'submitLabel' => 'Update Package',
        ])
    </section>
@endsection
