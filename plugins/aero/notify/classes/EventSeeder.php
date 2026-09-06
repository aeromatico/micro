<?php namespace Aero\Notify\Classes;

use Aero\Notify\Models\Event;
use Aero\Notify\Models\Rule;

/**
 * Siembra el catálogo. Es idempotente por code, así que puede correrse tantas
 * veces como haga falta: tras un despliegue que añade eventos nuevos, o a mano
 * con notify:seed-events.
 */
class EventSeeder
{
    protected int $created = 0;
    protected int $updated = 0;
    protected int $rules = 0;

    public function run(bool $withDefaultRules = true): array
    {
        foreach (EventCatalog::all() as $definition) {
            $event = $this->upsertEvent($definition);

            if ($withDefaultRules) {
                $this->seedDefaultRules($event);
            }
        }

        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'rules'   => $this->rules,
        ];
    }

    protected function upsertEvent(array $definition): Event
    {
        $event = Event::where('code', $definition['code'])->first();

        // Campos que describen el evento y su contrato: se refrescan siempre,
        // son propiedad del código, no del operador.
        $managed = [
            'source_plugin'    => $definition['source_plugin'] ?? null,
            'category'         => $definition['category'] ?? 'general',
            'name'             => $definition['name'],
            'description'      => $definition['description'] ?? null,
            'variables_schema' => $definition['variables_schema'] ?? [],
            'sample_context'   => $definition['sample_context'] ?? [],
            'default_channels'  => $definition['default_channels'] ?? [],
            'default_audiences' => $definition['default_audiences'] ?? [],
            'is_transactional' => $definition['is_transactional'] ?? true,
            'is_system'        => $definition['is_system'] ?? false,
        ];

        if (!$event) {
            $event = new Event;
            $event->code = $definition['code'];
            // priority e is_active solo se fijan al crear: si un operador los
            // ajustó, un redespliegue no debe pisarle la configuración.
            $event->priority = $definition['priority'] ?? 5;
            $event->is_active = true;
            $event->fill($managed);
            $event->save();

            $this->created++;

            return $event;
        }

        $event->fill($managed);

        if ($event->isDirty()) {
            $event->save();
            $this->updated++;
        }

        return $event;
    }

    /**
     * Reglas globales por defecto: el producto cartesiano de las audiencias y
     * los canales que el catálogo declara para el evento. Solo se crean si no
     * existen; nunca se reactivan ni se pisan.
     */
    protected function seedDefaultRules(Event $event): void
    {
        $audiences = (array) $event->default_audiences;
        $channels  = (array) $event->default_channels;

        if (!$audiences || !$channels) {
            return;
        }

        $sort = 0;

        foreach ($audiences as $audience) {
            foreach ($channels as $channel) {
                $sort++;

                $exists = Rule::global()
                    ->where('event_id', $event->id)
                    ->where('audience', $audience)
                    ->where('channel', $channel)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $rule = new Rule;
                $rule->tenant_id  = Rule::GLOBAL_TENANT;
                $rule->event_id   = $event->id;
                $rule->audience   = $audience;
                $rule->channel    = $channel;
                $rule->is_enabled = true;
                $rule->sort_order = $sort;
                $rule->save();

                $this->rules++;
            }
        }
    }
}
