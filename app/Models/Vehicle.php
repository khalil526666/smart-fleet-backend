<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand', 'model', 'license_plate', 'year', 'mileage', 'status', 'photo_path',
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) return null;
        return url(Storage::disk('public')->url($this->photo_path));
    }

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
        ];
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class)->orderByDesc('date');
    }

    public function maintenancePredictions(): HasMany
    {
        return $this->hasMany(\App\Models\MaintenancePrediction::class)->orderByDesc('created_at');
    }

    public function fuelRecords(): HasMany
    {
        return $this->hasMany(FuelRecord::class)->orderByDesc('date');
    }
}
