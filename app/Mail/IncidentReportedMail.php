<?php

namespace App\Mail;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IncidentReportedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Incident $incident,
        public User $chauffeur,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Incident signalé #{$this->incident->id} - Smart Fleet",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.incidents.reported',
            with: [
                'incident' => $this->incident,
                'chauffeur' => $this->chauffeur,
            ],
        );
    }
}

