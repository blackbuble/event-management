<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_token',
        'user_id',
        'event_id',
        'ticket_data',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'ticket_data' => 'array',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '<=', now());
    }

    // Helper methods
    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    public function isActive()
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    public function calculateTotal()
    {
        $total = 0;
        foreach ($this->ticket_data as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        return $total;
    }
}