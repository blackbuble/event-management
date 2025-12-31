<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'name', 'description', 'price', 'quantity',
        'quantity_sold', 'quantity_reserved', 'sale_starts', 'sale_ends',
        'min_per_order', 'max_per_order', 'is_active'
    ];

    protected $casts = [
        'sale_starts' => 'datetime',
        'sale_ends' => 'datetime',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function bookingTickets()
    {
        return $this->hasMany(BookingTicket::class);
    }

    // Helper methods
    public function isAvailable()
    {
        if (!$this->is_active) return false;
        
        $available = $this->quantity - $this->quantity_sold - $this->quantity_reserved;
        if ($available <= 0) return false;
        
        $now = now();
        if ($this->sale_starts && $now->lt($this->sale_starts)) return false;
        if ($this->sale_ends && $now->gt($this->sale_ends)) return false;
        
        return true;
    }

    public function remainingQuantity()
    {
        return max(0, $this->quantity - $this->quantity_sold - $this->quantity_reserved);
    }

    public function isFree()
    {
        return $this->price == 0;
    }
}