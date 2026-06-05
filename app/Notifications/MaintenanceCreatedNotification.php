<?php

namespace App\Notifications;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenanceCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Maintenance $maintenance,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $vehicle = $this->maintenance->relationLoaded('vehicle') ? $this->maintenance->vehicle : null;
        $plate = $vehicle?->license_plate;

        return [
            'kind' => 'maintenance_created',
            'title' => 'Maintenance enregistrée',
            'message' => $plate
                ? "Maintenance enregistrée pour le véhicule {$plate}."
                : 'Maintenance enregistrée.',
            'maintenance_id' => $this->maintenance->id,
            'vehicle_id' => $this->maintenance->vehicle_id,
            'created_at' => now()->toISOString(),
        ];
    }
}

