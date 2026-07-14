<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WebPush\WebPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->withCount('listings')
            ->when($request->filled('q'), function ($builder) use ($request): void {
                $term = $request->string('q')->toString();
                $builder->where(function ($nested) use ($term): void {
                    $nested
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('role'), function ($builder) use ($request): void {
                if ($request->string('role')->toString() === 'admin') {
                    $builder->where('is_admin', true);
                }

                if ($request->string('role')->toString() === 'user') {
                    $builder->where('is_admin', false);
                }
            })
            ->when($request->filled('status'), function ($builder) use ($request): void {
                if ($request->string('status')->toString() === 'blocked') {
                    $builder->where('is_blocked', true);
                }

                if ($request->string('status')->toString() === 'active') {
                    $builder->where('is_blocked', false);
                }
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id && $user->is_admin) {
            return back()->with('status', 'You cannot remove your own admin role.');
        }

        $user->update([
            'is_admin' => ! $user->is_admin,
        ]);

        return back()->with('status', 'Admin role updated successfully.');
    }

    public function toggleBlock(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->with('status', 'You cannot block your own account.');
        }

        if ($user->is_admin && ! $user->is_blocked) {
            return back()->with('status', 'Remove admin rights before blocking this account.');
        }

        $user->update([
            'is_blocked' => ! $user->is_blocked,
        ]);

        return back()->with('status', 'User status updated successfully.');
    }

    public function testPush(Request $request, User $user, WebPushService $webPushService): RedirectResponse
    {
        $activeCount = $user->pushSubscriptions()->where('is_active', true)->count();

        if ($activeCount === 0) {
            return back()->with('status', "No active push subscriptions found for {$user->name}.");
        }

        $webPushService->sendToUser($user, [
            'title' => 'Push Test ✓',
            'body' => "Test notification sent to {$user->name} by an admin.",
            'icon' => '/branding/unsell-icon-512.png',
            'badge' => '/branding/unsell-icon-512.png',
            'tag' => 'admin-test-push',
            'data' => [
                'url' => '/',
                'type' => 'test',
            ],
        ]);

        return back()->with('status', "Test push sent to {$user->name} ({$activeCount} device(s)).");
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:15'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_admin' => ['boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'password' => bcrypt($validated['password']),
            'is_admin' => $validated['is_admin'] ?? false,
            'is_blocked' => false,
        ]);

        return redirect()->route('admin.users.index')
            ->with('status', "User '{$user->name}' created successfully with email {$user->email}.");
    }

    public function export(Request $request)
    {
        $query = User::query();

        // Apply same filters as index
        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($nested) use ($term): void {
                $nested
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        if ($request->filled('role')) {
            if ($request->string('role')->toString() === 'admin') {
                $query->where('is_admin', true);
            }
            if ($request->string('role')->toString() === 'user') {
                $query->where('is_admin', false);
            }
        }

        if ($request->filled('status')) {
            if ($request->string('status')->toString() === 'blocked') {
                $query->where('is_blocked', true);
            }
            if ($request->string('status')->toString() === 'active') {
                $query->where('is_blocked', false);
            }
        }

        $users = $query->withCount('listings')->get();

        $csv = "Name,Email,Phone,City,State,Listings,Role,Status,Member Since,Last Seen\n";

        foreach ($users as $user) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s",%d,"%s","%s",%s,%s',
                str_replace('"', '""', $user->name),
                $user->email,
                $user->phone ?? '',
                $user->city ?? '',
                $user->state ?? '',
                $user->listings_count,
                $user->is_admin ? 'Admin' : 'User',
                $user->is_blocked ? 'Blocked' : 'Active',
                $user->created_at->format('M d, Y'),
                $user->last_seen_at?->format('M d, Y') ?? 'Never'
            ) . "\n";
        }

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="users_' . now()->format('Y-m-d_H-i-s') . '.csv"');
    }
}
