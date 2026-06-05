<?php

namespace App\Notifications;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IncidentReportedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Incident $incident,
        public User $chauffeur,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'incident_reported',
            'title' => 'New incident',
            'message' => "New incident reported by {$this->chauffeur->name}.",
            'incident_id' => $this->incident->id,
            'mission_id' => $this->incident->mission_id,
            'chauffeur_id' => $this->chauffeur->id,
            'created_at' => now()->toISOString(),
        ];
    }
}

