<?php

namespace App\Services;

use Pusher\Pusher;

class PusherService
{
    private ?Pusher $pusher = null;

    private function client(): ?Pusher
    {
        if ($this->pusher) return $this->pusher;

        $key = env('PUSHER_APP_KEY');
        $secret = env('PUSHER_APP_SECRET');
        $appId = env('PUSHER_APP_ID');
        $cluster = env('PUSHER_APP_CLUSTER', 'eu');

        if (! $key || ! $secret || ! $appId) {
            return null; // realtime disabled if not configured
        }

        $this->pusher = new Pusher($key, $secret, $appId, [
            'cluster' => $cluster,
            'useTLS' => true,
        ]);

        return $this->pusher;
    }

    public function notifyUser(int $userId, array $payload): void
    {
        $p = $this->client();
        if (! $p) return;

        // Private user channel
        $channel = "private-user.{$userId}";
        $p->trigger($channel, 'notification', $payload);
    }

    /** Live trajet point for assigned chauffeurs + fleet monitors (WebSocket / Pusher). */
    public function publishMissionTrajet(int $missionId, array $payload): void
    {
        $p = $this->client();
        if (! $p) return;

        $p->trigger("private-mission.{$missionId}", 'trajet.created', $payload);
        $p->trigger('private-fleet.live', 'trajet.created', $payload);
    }

    /** Fleet rule violations (overspeed, idle, delayed…). */
    public function publishFleetAlert(array $payload): void
    {
        $p = $this->client();
        if (! $p) return;

        $p->trigger('private-fleet.live', 'fleet.alert', $payload);
    }

    public function auth(string $socketId, string $channelName): array
    {
        $p = $this->client();
        if (! $p) return ['auth' => ''];

        return $p->authorizeChannel($channelName, $socketId);
    }
}

