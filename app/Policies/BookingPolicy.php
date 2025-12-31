<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookingPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin and staff can view all bookings
        return $user->hasRole(['admin', 'staff']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Booking $booking): bool
    {
        // User can view their own bookings
        if ($user->id === $booking->user_id) {
            return true;
        }

        // Admin and staff can view any booking
        if ($user->hasRole(['admin', 'staff'])) {
            return true;
        }

        // Event organizer can view bookings for their events
        return $user->id === $booking->event->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create bookings
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Booking $booking): bool
    {
        // Admin can update any booking
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can update their own booking
        return $user->id === $booking->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Booking $booking): bool
    {
        // Admin can delete any booking
        if ($user->hasRole('admin')) {
            return true;
        }

        // User can cancel/delete their own booking if allowed
        return $user->id === $booking->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Booking $booking): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Booking $booking): bool
    {
        return $user->hasRole('admin');
    }
}
