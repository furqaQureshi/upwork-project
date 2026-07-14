<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if ($request->filled('token')) {
            $validated = $request->validate([
                'provider' => ['nullable', 'string', 'in:fcm'],
                'token' => ['required', 'string', 'max:512'],
                'permission' => ['nullable', 'string', 'max:32'],
            ]);

            $token = $validated['token'];
            $subscription = PushSubscription::query()
                ->where('provider', 'fcm')
                ->where(function ($builder) use ($token): void {
                    $builder
                        ->where('device_token', $token)
                        ->orWhere('endpoint', 'fcm:'.$token);
                })
                ->first() ?? new PushSubscription();

            $subscription->fill([
                'user_id' => $request->user()->id,
                'provider' => 'fcm',
                'endpoint' => 'fcm:'.$token,
                'device_token' => $token,
                'public_key' => 'fcm',
                'auth_token' => 'fcm',
                'content_encoding' => 'fcm',
                'is_active' => true,
                'last_used_at' => now(),
                'meta' => [
                    'permission' => $validated['permission'] ?? null,
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                ],
            ]);
        } else {
            $validated = $request->validate([
                'endpoint' => ['required', 'string', 'max:2048'],
                'keys' => ['required', 'array'],
                'keys.p256dh' => ['required', 'string', 'max:1024'],
                'keys.auth' => ['required', 'string', 'max:1024'],
                'expirationTime' => ['nullable', 'integer'],
                'contentEncoding' => ['nullable', 'string', 'max:32'],
            ]);

            $subscription = PushSubscription::query()
                ->where('endpoint', $validated['endpoint'])
                ->first() ?? new PushSubscription();

            $subscription->fill([
                'user_id' => $request->user()->id,
                'provider' => 'webpush',
                'endpoint' => $validated['endpoint'],
                'device_token' => null,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aesgcm',
                'is_active' => true,
                'last_used_at' => now(),
                'meta' => [
                    'expiration_time' => $validated['expirationTime'] ?? null,
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                ],
            ]);
        }

        $subscription->save();

        return response()->json([
            'ok' => true,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        if ($request->filled('token')) {
            $validated = $request->validate([
                'token' => ['required', 'string', 'max:512'],
            ]);

            $request->user()
                ->pushSubscriptions()
                ->where('provider', 'fcm')
                ->where(function ($builder) use ($validated): void {
                    $builder
                        ->where('device_token', $validated['token'])
                        ->orWhere('endpoint', 'fcm:'.$validated['token']);
                })
                ->update([
                    'is_active' => false,
                    'last_used_at' => now(),
                ]);
        } else {
            $validated = $request->validate([
                'endpoint' => ['required', 'string', 'max:2048'],
            ]);

            $request->user()
                ->pushSubscriptions()
                ->where('endpoint', $validated['endpoint'])
                ->update([
                    'is_active' => false,
                    'last_used_at' => now(),
                ]);
        }

        return response()->json([
            'ok' => true,
        ]);
    }
}
