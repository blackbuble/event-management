<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HasUuid;
use Spatie\Permission\Traits\HasRoles;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, SoftDeletes, HasUuid, HasRoles, HasFactory;

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
        'password' => 'hashed',
        'otp_expires_at' => 'datetime',
    ];

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

    public function ownedOrganizations()
    {
        return $this->hasMany(Organization::class, 'user_id');
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'model_has_roles', 'model_id', 'organization_id')
            ->where('model_type', self::class)
            ->withPivot('role_id');
    }
}