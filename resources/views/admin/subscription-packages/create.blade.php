@extends('admin.layout')

@section('title', 'Create Subscription Package')

@section('content')
    <section class="mx-auto max-w-5xl space-y-5">
        <div>
            <h2 class="font-display text-2xl font-bold text-slate-900">Create Subscription Package</h2>
            <p class="text-sm text-slate-600">Define pricing and package settings for listing or featured ad subscriptions.</p>
        </div>

        @include('admin.subscription-packages.form', [
            'package' => null,
            'categories' => $categories,
            'action' => route('admin.subscription-packages.store'),
            'submitLabel' => 'Create Package',
        ])
    </section>
@endsection
