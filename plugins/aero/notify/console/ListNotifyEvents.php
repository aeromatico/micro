<?php namespace Aero\Notify\Console;

use Aero\Notify\Models\Event;
use Aero\Notify\Models\Rule;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Inspección del catálogo desde la consola. Sirve para verificar un despliegue
 * sin entrar al backend y para ver de un vistazo qué evento quedó sin reglas.
 */
class ListNotifyEvents extends Command
{
    protected $name = 'notify:events';

    protected $description = 'Lista los eventos del catálogo de notificaciones.';

    public function handle(): int
    {
        $query = Event::orderBy('source_plugin')->orderBy('code');

        if ($group = $this->option('group')) {
            $query->where('source_plugin', 'like', "%{$group}%");
        }

        if ($this->option('active')) {
            $query->active();
        }

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->warn('No hay eventos en el catálogo. Corré notify:seed-events.');

            return 1;
        }

        $tenantId = (int) $this->option('tenant');

        $rows = $events->map(function (Event $event) use ($tenantId) {
            $rules = Rule::effectiveFor($event, $tenantId);

            return [
                $event->code,
                $event->category,
                $event->priority,
                $event->is_active ? 'sí' : 'no',
                $rules->isEmpty()
                    ? '—'
                    : $rules->map(fn ($r) => "{$r->audience}/{$r->channel}")->implode(', '),
            ];
        })->all();

        $this->table(
            ['Código', 'Categoría', 'Prio', 'Activo', $tenantId ? "Reglas (tenant {$tenantId})" : 'Reglas globales'],
            $rows
        );

        $this->line('');
        $this->info("{$events->count()} eventos en el catálogo.");

        $orphans = $events->filter(fn (Event $e) => Rule::effectiveFor($e, $tenantId)->isEmpty());

        if ($orphans->isNotEmpty()) {
            $this->warn("{$orphans->count()} sin ninguna regla de entrega: no notificarán nada.");
        }

        return 0;
    }

    protected function getOptions(): array
    {
        return [
            ['group',  null, InputOption::VALUE_REQUIRED, 'Filtrar por plugin de origen, ej. Aero.Crm'],
            ['tenant', null, InputOption::VALUE_REQUIRED, 'Mostrar las reglas efectivas de este tenant', 0],
            ['active', null, InputOption::VALUE_NONE,     'Solo los eventos activos'],
        ];
    }
}
