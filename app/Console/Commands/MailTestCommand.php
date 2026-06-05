<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {email : Adresse qui recevra le mail de test}';

    protected $description = 'Envoie un email simple pour vérifier la config SMTP (.env).';

    public function handle(): int
    {
        $email = $this->argument('email');
        $host = config('mail.mailers.smtp.host');
        $real = config('mail.real_inbox') ? 'livraison réelle (Gmail / autre)' : 'sandbox Mailtrap';

        try {
            Mail::raw(
                "Test Smart Fleet.\n\nMode : {$real}\nServeur SMTP : {$host}\n",
                function ($m) use ($email) {
                    $m->to($email)->subject('Smart Fleet — test SMTP');
                }
            );
            $this->info("OK — message envoyé vers {$email}.");
            if (config('mail.real_inbox')) {
                $this->line('Vérifie la boîte mail (et les indésirables).');
            } else {
                $this->line('Avec Mailtrap : ouvre mailtrap.io → Email Testing → Messages (pas la boîte Gmail).');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
