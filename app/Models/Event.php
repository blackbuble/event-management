<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'title', 'slug', 'description', 'image',
        'venue_name', 'venue_address', 'latitude', 'longitude',
        'start_date', 'end_date', 'status', 'type', 'category', 'city', 'meeting_link', 'capacity',
        'whatsapp_enabled',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'whatsapp_enabled' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->title);

                // Ensure unique slug
                $count = static::where('slug', 'like', $event->slug.'%')->count();
                if ($count > 0) {
                    $event->slug = $event->slug.'-'.($count + 1);
                }
            }
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categoryModel()
    {
        return $this->belongsTo(Category::class, 'category', 'slug');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reservations()
    {
        return $this->hasMany(BookingReservation::class);
    }

    public function reviews()
    {
        return $this->hasMany(EventReview::class);
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>=', now());
    }

    // Helper methods
    public function availableTickets()
    {
        return $this->tickets()
            ->where('is_active', true)
            ->whereRaw('quantity > (quantity_sold + quantity_reserved)');
    }

    public function isFull()
    {
        if (! $this->capacity) {
            return false;
        }

        $totalConfirmed = $this->relationLoaded('bookings')
            ? $this->bookings->count()
            : $this->bookings()->where('status', 'confirmed')->count();

        return $totalConfirmed >= $this->capacity;
    }

    public function totalRevenue()
    {
        return $this->bookings()
            ->where('payment_status', 'paid')
            ->sum('total_amount');
    }
}
