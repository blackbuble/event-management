<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Event;
use App\Models\User;
use App\Models\WhatsAppTopUp;

class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        return [
            'users' => [
                'total' => User::query()->count(),
                'organizers' => User::query()->role('organizer')->count(),
                'attendees' => User::query()->role('attendee')->count(),
            ],
            'events' => [
                'total' => Event::query()->count(),
                'published' => Event::query()->where('status', 'published')->count(),
                'draft' => Event::query()->where('status', 'draft')->count(),
            ],
            'bookings' => [
                'total' => Booking::query()->count(),
                'confirmed' => Booking::query()->where('status', 'confirmed')->count(),
                'revenue' => (float) Booking::query()->where('payment_status', 'paid')->sum('total_amount'),
            ],
            'whatsapp' => [
                'topups' => WhatsAppTopUp::query()->count(),
                'quota_sold' => (int) WhatsAppTopUp::query()->where('status', 'paid')->sum('quota'),
            ],
            'recent_users' => User::query()->latest()->limit(5)->get(['id', 'name', 'email', 'created_at'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /**
     * Paginated, searchable/filterable user list for the admin users table.
     *
     * @param  array{search?: string, role?: string, status?: string}  $filters
     * @return array{users: array<int, array<string, mixed>>, pagination: array<string, int>, filters: array<string, string>}
     */
    public function users(array $filters = [], int $perPage = 15): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = (string) ($filters['role'] ?? '');
        $status = (string) ($filters['status'] ?? '');

        $paginator = User::query()
            ->with('roles')
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when(in_array($role, ['admin', 'organizer', 'staff', 'attendee'], true), fn ($query) => $query->role($role))
            ->when($status === 'suspended', fn ($query) => $query->whereNotNull('suspended_at'))
            ->when($status === 'active', fn ($query) => $query->whereNull('suspended_at'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return [
            'users' => $paginator->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
                'suspended' => $user->isSuspended(),
                'created_at' => $user->created_at?->toIso8601String(),
            ])->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => ['search' => $search, 'role' => $role, 'status' => $status],
        ];
    }
}
