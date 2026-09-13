<?php

use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Seeder;

/**
 * 'sites.contact.submitted' suma whatsapp/telegram/sms a default_channels
 * (EventCatalog), como parte de migrar el contacto al gateway único (ver
 * Aero\Sites\Jobs\DispatchContactNotification). EventSeeder crea las Rules
 * globales que faltan sin tocar las de email/inapp que ya existían.
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();
    }
};
