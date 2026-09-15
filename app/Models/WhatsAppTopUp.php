<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppTopUp extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_topups';

    protected $fillable = [
        'user_id', 'package', 'amount', 'quota', 'payment_method', 'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quota' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
