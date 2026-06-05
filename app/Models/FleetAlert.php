<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetAlert extends Model
{
    protected $fillable = [
        'mission_id',
        'type',
        'message',
        'meta',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }
}
