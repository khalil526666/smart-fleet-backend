<?php

namespace App\Notifications;

use App\Models\MaintenancePrediction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MaintenancePredictionAlert extends Notification
{
    use Queueable;

    public function __construct(public MaintenancePrediction $prediction) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $vehicle = $this->prediction->vehicle;
        $brandModel = "{$vehicle->brand} {$vehicle->model}";
        $plate = $vehicle->license_plate;
        $days = $this->prediction->predicted_days;
        $urgency = $this->prediction->urgency_level;

        $message = match ($urgency) {
            'eleve'  => "URGENT — Le véhicule {$brandModel} ({$plate}) nécessitera une maintenance dans {$days} jour(s).",
            'moyen'  => "Le véhicule {$brandModel} ({$plate}) approche de son échéance de maintenance ({$days} jours).",
            default  => "Prédiction de maintenance pour {$brandModel} ({$plate}) : {$days} jours.",
        };

        return [
            'kind'          => 'maintenance_prediction_alert',
            'title'         => 'Alerte maintenance prédictive',
            'message'       => $message,
            'vehicle_id'    => $vehicle->id,
            'license_plate' => $plate,
            'brand_model'   => $brandModel,
            'predicted_days'  => $days,
            'predicted_date'  => $this->prediction->predicted_date->toDateString(),
            'urgency_level'   => $urgency,
            'prediction_id'   => $this->prediction->id,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
