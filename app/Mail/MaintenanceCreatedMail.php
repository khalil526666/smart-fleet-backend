<?php

namespace App\Mail;

use App\Models\Maintenance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MaintenanceCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Maintenance $maintenance,
    ) {}

    public function envelope(): Envelope
    {
        $vehicle = $this->maintenance->relationLoaded('vehicle') ? $this->maintenance->vehicle : null;
        $plate = $vehicle?->license_plate;
        $subject = $plate
            ? "Maintenance enregistrée ({$plate}) - Smart Fleet"
            : 'Maintenance enregistrée - Smart Fleet';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.maintenance.created',
            with: [
                'maintenance' => $this->maintenance,
                'vehicle' => $this->maintenance->vehicle ?? null,
            ],
        );
    }
}

