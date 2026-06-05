<?php

namespace App\Services;

use App\Mail\IncidentReportConfirmationMail;
use App\Mail\IncidentReportedMail;
use App\Mail\MaintenanceCreatedMail;
use App\Mail\MissionAssignedToChauffeurMail;
use App\Mail\MissionCompletedChauffeurNoticeMail;
use App\Mail\MissionCompletedMail;
use App\Mail\MissionStartedChauffeurNoticeMail;
use App\Mail\MissionStartedMail;
use App\Mail\PasswordResetMail;
use App\Models\EmailLog;
use App\Models\Incident;
use App\Models\Maintenance;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailService
{
    /**
     * Minimal config check to provide a clear error to the frontend.
     * (Avoids silent failures when SMTP isn't configured.)
     */
    public function assertConfigured(): void
    {
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $from = config('mail.from.address');
        $user = config('mail.mailers.smtp.username');
        $pass = config('mail.mailers.smtp.password');

        if (! $host || ! $port || ! $from || ! $user || $pass === null || $pass === '') {
            $hint = config('mail.real_inbox')
                ? 'MAIL_USE_REAL_INBOX=true: remplis MAIL_REAL_HOST, MAIL_REAL_PORT, MAIL_REAL_USERNAME, MAIL_REAL_PASSWORD et MAIL_REAL_FROM_ADDRESS (ou laisse FROM = USERNAME).'
                : 'MAIL_USE_REAL_INBOX=false: remplis MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD et MAIL_FROM_ADDRESS.';

            throw new \RuntimeException('SMTP is not configured. '.$hint);
        }
    }

    public function sendPasswordReset(User $user, string $resetUrl): EmailLog
    {
        $this->assertConfigured();

        $mailable = new PasswordResetMail(user: $user, resetUrl: $resetUrl);
        return $this->sendMailable(
            type: 'password_reset',
            to: $user,
            subject: $mailable->envelope()->subject ?? 'Password reset',
            mailable: $mailable,
            related: $user,
            payload: ['reset_url' => $resetUrl],
        );
    }

    public function sendMissionStarted(User $to, Mission $mission, User $chauffeur): EmailLog
    {
        $this->assertConfigured();

        $mailable = new MissionStartedMail(mission: $mission, chauffeur: $chauffeur);
        return $this->sendMailable(
            type: 'mission_started',
            to: $to,
            subject: $mailable->envelope()->subject ?? 'Mission started',
            mailable: $mailable,
            related: $mission,
            payload: [
                'mission_id' => $mission->id,
                'mission_title' => $mission->title,
                'chauffeur_id' => $chauffeur->id,
                'chauffeur_name' => $chauffeur->name,
            ],
        );
    }

    public function sendMissionCompleted(User $to, Mission $mission, User $chauffeur): EmailLog
    {
        $this->assertConfigured();

        $mailable = new MissionCompletedMail(mission: $mission, chauffeur: $chauffeur);
        return $this->sendMailable(
            type: 'mission_completed',
            to: $to,
            subject: $mailable->envelope()->subject ?? 'Mission completed',
            mailable: $mailable,
            related: $mission,
            payload: [
                'mission_id' => $mission->id,
                'mission_title' => $mission->title,
                'chauffeur_id' => $chauffeur->id,
                'chauffeur_name' => $chauffeur->name,
            ],
        );
    }

    public function sendIncidentReported(User $to, Incident $incident, User $chauffeur): EmailLog
    {
        $this->assertConfigured();

        $mailable = new IncidentReportedMail(incident: $incident, chauffeur: $chauffeur);
        return $this->sendMailable(
            type: 'incident_reported',
            to: $to,
            subject: $mailable->envelope()->subject ?? 'Incident reported',
            mailable: $mailable,
            related: $incident,
            payload: [
                'incident_id' => $incident->id,
                'mission_id' => $incident->mission_id,
                'chauffeur_id' => $chauffeur->id,
                'chauffeur_name' => $chauffeur->name,
            ],
        );
    }

    /**
     * Copie au chauffeur après qu’il a démarré une mission (les admins reçoivent un autre mail).
     */
    public function sendMissionStartedNoticeToChauffeur(User $chauffeur, Mission $mission): EmailLog
    {
        $this->assertConfigured();

        $mailable = new MissionStartedChauffeurNoticeMail(mission: $mission, chauffeur: $chauffeur);
        return $this->sendMailable(
            type: 'mission_started_chauffeur_notice',
            to: $chauffeur,
            subject: $mailable->envelope()->subject ?? 'Mission démarrée',
            mailable: $mailable,
            related: $mission,
            payload: ['mission_id' => $mission->id],
        );
    }

    /**
     * Copie au chauffeur après qu’il a terminé une mission.
     */
    public function sendMissionCompletedNoticeToChauffeur(User $chauffeur, Mission $mission): EmailLog
    {
        $this->assertConfigured();

        $mailable = new MissionCompletedChauffeurNoticeMail(mission: $mission, chauffeur: $chauffeur);
        return $this->sendMailable(
            type: 'mission_completed_chauffeur_notice',
            to: $chauffeur,
            subject: $mailable->envelope()->subject ?? 'Mission terminée',
            mailable: $mailable,
            related: $mission,
            payload: ['mission_id' => $mission->id],
        );
    }

    /**
     * Email au conducteur (utilisateur chauffeur) lorsqu’une mission lui est assignée.
     */
    public function sendMissionAssignedToChauffeur(User $chauffeur, Mission $mission): EmailLog
    {
        $this->assertConfigured();

        $mailable = new MissionAssignedToChauffeurMail(mission: $mission, chauffeur: $chauffeur);
        return $this->sendMailable(
            type: 'mission_assigned_chauffeur',
            to: $chauffeur,
            subject: $mailable->envelope()->subject ?? 'Mission assignée',
            mailable: $mailable,
            related: $mission,
            payload: [
                'mission_id' => $mission->id,
                'mission_title' => $mission->title,
                'chauffeur_id' => $chauffeur->id,
            ],
        );
    }

    /**
     * Accusé de réception envoyé au conducteur après signalement d’incident.
     */
    public function sendIncidentReportConfirmationToChauffeur(User $chauffeur, Incident $incident): EmailLog
    {
        $this->assertConfigured();

        $mailable = new IncidentReportConfirmationMail(incident: $incident, chauffeur: $chauffeur);
        return $this->sendMailable(
            type: 'incident_report_confirmation_chauffeur',
            to: $chauffeur,
            subject: $mailable->envelope()->subject ?? 'Incident enregistré',
            mailable: $mailable,
            related: $incident,
            payload: [
                'incident_id' => $incident->id,
                'mission_id' => $incident->mission_id,
            ],
        );
    }

    public function sendMaintenanceCreated(User $to, Maintenance $maintenance): EmailLog
    {
        $this->assertConfigured();

        $mailable = new MaintenanceCreatedMail(maintenance: $maintenance);
        return $this->sendMailable(
            type: 'maintenance_alert',
            to: $to,
            subject: $mailable->envelope()->subject ?? 'Maintenance alert',
            mailable: $mailable,
            related: $maintenance,
            payload: [
                'maintenance_id' => $maintenance->id,
                'vehicle_id' => $maintenance->vehicle_id,
                'maintenance_type' => $maintenance->maintenance_type,
                'date' => optional($maintenance->date)->toDateString(),
            ],
        );
    }

    private function sendMailable(
        string $type,
        User $to,
        string $subject,
        object $mailable,
        ?object $related = null,
        ?array $payload = null,
    ): EmailLog {
        $log = new EmailLog([
            'type' => $type,
            'to_email' => (string) $to->email,
            'to_name' => $to->name ?: null,
            'subject' => $subject,
            'status' => 'skipped',
            'payload' => $payload,
        ]);

        if ($related) {
            $log->related()->associate($related);
        }

        try {
            if (! $to->email) {
                $log->status = 'skipped';
                $log->error_message = 'Recipient has no email.';
                $log->save();
                return $log;
            }

            Mail::to([$to->email => $to->name ?: $to->email])->send($mailable);

            $log->status = 'sent';
            $log->sent_at = now();
            $log->save();
            return $log;
        } catch (Throwable $e) {
            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->save();
            return $log;
        }
    }
}

