<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BookingTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'ticket_id', 'ticket_code', 'quantity',
        'price', 'attendee_name', 'attendee_email',
        'checked_in', 'checked_in_at'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'checked_in' => 'boolean',
        'checked_in_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($bookingTicket) {
            if (empty($bookingTicket->ticket_code)) {
                $bookingTicket->ticket_code = 'TK-' . strtoupper(Str::random(12));
            }
        });
    }

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // Helper methods
    public function canCheckIn()
    {
        return !$this->checked_in 
            && $this->booking->status === 'confirmed'
            && $this->booking->payment_status === 'paid';
    }
}