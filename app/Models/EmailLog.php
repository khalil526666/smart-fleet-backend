<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    protected $fillable = [
        'type',
        'to_email',
        'to_name',
        'subject',
        'status',
        'error_message',
        'related_type',
        'related_id',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}

