<?php namespace Aero\Notify\Console;

use Aero\Notify\Classes\Notify;
use Aero\Notify\Models\Event;
use Illuminate\Console\Command;

class TestNotify extends Command
{
    protected $signature = 'notify:test {event : código del evento} {--tenant=0} {--user= : id de usuario backend (actor)} {--email=} {--phone=}';

    protected $description = 'Dispara un evento con su sample_context y muestra las entregas resultantes.';

    public function handle(): int
    {
        $event = Event::where('code', $this->argument('event'))->first();

        if (!$event) {
            $this->error('Evento inexistente.');
            return 1;
        }

        $actor = array_filter([
            'user_id' => $this->option('user'),
            'email'   => $this->option('email'),
            'phone'   => $this->option('phone'),
            'name'    => 'Prueba',
        ]);

        $deliveries = Notify::fire($event->code, (array) $event->sample_context, [
            'tenant_id' => (int) $this->option('tenant'),
            'actor'     => $actor,
            'adhoc'     => [$actor],
            'sync'      => true,
        ]);

        $this->table(['#', 'Canal', 'Audiencia', 'Dirección', 'Estado', 'Detalle'], collect($deliveries)->map(fn ($d) => [
            $d->id, $d->channel, $d->audience, $d->address, $d->status, $d->error,
        ])->all());

        return 0;
    }
}
