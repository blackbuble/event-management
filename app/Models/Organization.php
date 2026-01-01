<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUuid;

class Organization extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'logo',
        'branding',
        'user_id',
    ];

    protected $casts = [
        'branding' => 'array',
    ];

    /**
     * Get the owner of the organization.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all users who have a role in this organization.
     */
    public function users()
    {
        return $this->whereHas('roles', function ($query) {
             $query->where('organization_id', $this->id);
        });
        // Note: This is pseudo-code for the inverse relationships.
        // A cleaner way often involves a direct relation or using the pivot table if we want efficient querying.
        // The standard Spatie way doesn't strictly require a pivot model, but querying 'users' of a team is complex without it.
        // Let's implement a simpler "members" retrieval via the permission tables manually or a dedicated relationship if we add a pivot.
    }
}
