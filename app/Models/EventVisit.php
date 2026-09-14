<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventVisit extends Model
{
    protected $fillable = [
        'event_id', 'ip', 'country', 'city', 'device', 'os', 'user_agent',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
