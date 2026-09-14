<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'bio',
        'avatar',
        'website',
        'uuid',
        'social_avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'social_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'password' => 'hashed',
        'otp_expires_at' => 'datetime',
        'whatsapp_quota' => 'integer',
    ];

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reservations()
    {
        return $this->hasMany(BookingReservation::class);
    }
}
