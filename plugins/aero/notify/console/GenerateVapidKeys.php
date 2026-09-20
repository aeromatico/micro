<?php namespace Aero\Notify\Console;

use Aero\Notify\Models\PushSettings;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'notify:vapid {--force : Regenera aunque ya existan (invalida todas las suscripciones)}';

    protected $description = 'Genera las claves VAPID globales para Web Push.';

    public function handle(): int
    {
        $settings = PushSettings::instance();

        if ($settings->vapid_public && !$this->option('force')) {
            $this->info('Ya hay claves VAPID. Clave pública: ' . $settings->vapid_public);
            return 0;
        }

        $keys = VAPID::createVapidKeys();

        $settings->vapid_public  = $keys['publicKey'];
        $settings->vapid_private = $keys['privateKey'];
        $settings->subject       = $settings->subject ?: 'mailto:' . (config('mail.from.address') ?: 'admin@example.com');
        $settings->save();

        $this->info('Claves VAPID generadas. Clave pública: ' . $keys['publicKey']);

        return 0;
    }
}
