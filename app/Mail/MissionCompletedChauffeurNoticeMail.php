<?php

namespace App\Mail;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MissionCompletedChauffeurNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Mission $mission,
        public User $chauffeur,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Confirmation — Mission terminée : {$this->mission->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.missions.completed-chauffeur-notice',
            with: [
                'mission' => $this->mission,
                'chauffeur' => $this->chauffeur,
            ],
        );
    }
}
