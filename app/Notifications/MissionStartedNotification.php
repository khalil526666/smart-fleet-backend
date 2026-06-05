<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MissionStartedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Mission $mission,
        public User $chauffeur,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'mission_started',
            'title' => 'Mission started',
            'message' => "Mission \"{$this->mission->title}\" started by {$this->chauffeur->name}.",
            'mission_id' => $this->mission->id,
            'chauffeur_id' => $this->chauffeur->id,
            'created_at' => now()->toISOString(),
        ];
    }
}

