<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePrediction extends Model
{
    protected $fillable = [
        'vehicle_id',
        'predicted_days',
        'predicted_date',
        'urgency_level',
        'features_snapshot',
        'confidence_note',
        'model_version',
    ];

    protected function casts(): array
    {
        return [
            'predicted_date'    => 'date',
            'features_snapshot' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
