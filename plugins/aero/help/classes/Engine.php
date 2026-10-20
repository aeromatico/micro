<?php namespace Aero\Help\Classes;

use Aero\Help\Models\Flow;
use Aero\Help\Models\Session;
use Aero\Help\Models\Topic;
use BackendAuth;

/**
 * Motor de la conversación de ayuda. Sin IA: interpreta los pasos de un
 * Flow, abre temas, busca en la documentación y deja el escalado a una
 * persona siempre a un paso. Cada respuesta es una lista de bloques que el
 * panel sabe dibujar (text, buttons, step, doc, guide, video, link, results,
 * escalate). Las opciones que llegan del navegador se validan contra el
 * flujo guardado: el cliente nunca decide a qué paso se salta.
 */
class Engine
{
    protected const MAX_STEPS_PER_TURN = 30;

    protected array $blocks = [];

    public function __construct(protected Session $session)
    {
    }

    public static function start(string $url, ?string $area): array
    {
        $user = BackendAuth::getUser();
        $session = Session::create([
            'tenant_id'  => CurrentTenant::id(),
            'user_id'    => $user->id,
            'area'       => $area ? mb_substr($area, 0, 150) : null,
            'url'        => mb_substr($url, 0, 1000),
            'status'     => 'open',
            'state'      => ['waiting' => null, 'answers' => []],
            'transcript' => [],
        ]);

        return (new static($session))->greeting();
    }

    /** La sesión solo existe para quien la abrió. */
    public static function find(int $id): ?static
    {
        $session = Session::where('user_id', BackendAuth::getUser()?->id)->find($id);

        return $session ? new static($session) : null;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function greeting(): array
    {
        $screen = ScreenContext::parse((string) $this->session->url);
        $name = trim((string) BackendAuth::getUser()?->first_name);

        $this->text(($name !== '' ? "Hola, {$name}. " : 'Hola. ') . 'Soy Mia, tu asistente de Market. Dime qué quieres lograr, o elige una opción.');
        $this->menu($screen);

        return $this->finish();
    }

    public function respond(string $action, string $value = ''): array
    {
        $value = trim($value);
        $s = $this->session;

        if ($s->status === 'escalated' && $action === 'text') {
            $this->text('Tu consulta ya está con el equipo. Si quieres, puedes empezar otra duda.');
            $this->buttons([['Nueva duda', 'menu']]);

            return $this->finish();
        }

        if ($action === 'text') {
            $s->remember('u', $value);
            $waiting = $this->waitingStep();
            if ($waiting && $waiting['type'] === 'ask') {
                $this->saveAnswer($waiting, $value, $value);
                $this->advance($waiting['next'] ?? null);
            } else {
                $this->search($value);
            }

            return $this->finish();
        }

        if ($value === 'menu') {
            $this->resetFlow();
            $this->menu(ScreenContext::parse((string) $s->url));
        } elseif ($value === 'resolved') {
            $this->resolve();
        } elseif ($value === 'unresolved') {
            $this->unresolved();
        } elseif ($value === 'escalate') {
            $this->escalationOptions();
        } elseif (str_starts_with($value, 'opt:')) {
            $this->choose(substr($value, 4));
        } elseif (str_starts_with($value, 'card:')) {
            $this->card(substr($value, 5));
        } else {
            $this->text('No entendí esa opción. Escribe tu duda o vuelve al menú.');
            $this->buttons([['Ver el menú', 'menu']]);
        }

        return $this->finish();
    }

    /* ---------- salida ---------- */

    protected function text(string $text): void
    {
        $this->blocks[] = ['type' => 'text', 'text' => $text];
        $this->session->remember('b', $text);
    }

    /** @param array<int, array{0: string, 1: string}> $items [etiqueta, valor] */
    protected function buttons(array $items): void
    {
        $this->blocks[] = ['type' => 'buttons', 'items' => array_map(fn ($i) => ['label' => $i[0], 'value' => $i[1]], $items)];
    }

    protected function add(?array $block): void
    {
        if ($block) {
            $this->blocks[] = $block;
            $this->session->remember('b', '[' . $block['type'] . '] ' . ($block['title'] ?? $block['label'] ?? ''));
        }
    }

    protected function finish(): array
    {
        $this->session->save();
        $hint = ($w = $this->waitingStep()) && $w['type'] === 'ask' ? (string) ($w['placeholder'] ?? 'Escribe tu respuesta…') : null;
        $blocks = $this->blocks;
        $this->blocks = [];   // cada turno devuelve solo lo suyo

        return [
            'session' => $this->session->id,
            'status'  => $this->session->status,
            'hint'    => $hint,
            'channels' => array_column(Escalation::available(), 'via'),
            'blocks'  => $blocks,
        ];
    }

    /* ---------- menú, temas y tarjetas ---------- */

    protected function menu(array $screen): void
    {
        $items = [];
        foreach (ScreenContext::suggestions($screen) as $s) {
            $items[] = [$s['title'], 'card:' . $s['kind'] . ':' . $s['id']];
        }

        if ($items) {
            $this->text('Para esta pantalla puedo ayudarte con:');
            $this->buttons($items);
        }

        $general = Topic::published()->whereNull('screens')->orderBy('sort_order')->limit(4)->get();
        if ($general->isNotEmpty()) {
            $this->text($items ? 'O con algo más general:' : 'Puedo ayudarte con:');
            $this->buttons($general->map(fn ($t) => [$t->title, 'card:topic:' . $t->id])->all());
        } elseif (!$items) {
            $this->text('Escribe tu duda y busco en la documentación.');
        }

        $this->buttons([['Hablar con una persona', 'escalate']]);
    }

    protected function card(string $ref): void
    {
        [$kind, $id] = array_pad(explode(':', $ref, 2), 2, '');
        $id = (int) $id;

        match ($kind) {
            'topic' => $this->openTopic($id),
            'doc'   => $this->openCard(Cards::doc($id)),
            'guide' => $this->openCard(Cards::guide($id)),
            default => $this->text('Ese contenido ya no está disponible.'),
        };
    }

    protected function openCard(?array $card): void
    {
        if (!$card) {
            $this->text('Ese contenido ya no está disponible.');

            return;
        }

        $this->add($card);
        $this->afterContent();
    }

    protected function openTopic(int $id): void
    {
        $topic = Topic::published()->find($id);
        if (!$topic) {
            $this->text('Ese tema ya no está disponible.');

            return;
        }

        $this->session->topic_id = $topic->id;
        $this->text('**' . $topic->title . '**' . ($topic->summary ? "\n" . $topic->summary : ''));

        $flow = $topic->flow_id ? Flow::published()->find($topic->flow_id) : null;
        if ($flow && $this->startFlow($flow)) {
            return;
        }

        $cards = array_filter([
            $topic->guide_id ? Cards::guide((int) $topic->guide_id) : null,
            $topic->article_id ? Cards::doc((int) $topic->article_id) : null,
            Cards::video($topic->plugin),
        ]);
        array_map(fn ($c) => $this->add($c), $cards);

        if (!$cards) {
            $this->text('Todavía no tengo contenido publicado para este tema.');
        }
        $this->afterContent();
    }

    protected function afterContent(): void
    {
        $this->buttons([['Se resolvió', 'resolved'], ['Necesito más ayuda', 'unresolved'], ['Ver el menú', 'menu']]);
    }

    /* ---------- búsqueda ---------- */

    protected function search(string $query): void
    {
        $results = Search::run($query);
        Search::log($query, count($results), $this->session->area);

        if (!$results) {
            $this->text('No encontré nada sobre eso en la documentación. Ya lo anoté para que lo cubramos. ¿Quieres hablar con una persona?');
            $this->buttons([['Hablar con una persona', 'escalate'], ['Ver el menú', 'menu']]);

            return;
        }

        $this->text('Esto encontré:');
        $this->blocks[] = [
            'type'  => 'results',
            'items' => array_map(fn ($r) => [
                'label' => $r['title'], 'summary' => $r['summary'] ? mb_substr(strip_tags((string) $r['summary']), 0, 120) : null,
                'value' => 'card:' . $r['kind'] . ':' . $r['id'], 'kind' => $r['kind'],
            ], $results),
        ];
        $this->buttons([['Nada de esto me sirve', 'unresolved']]);
    }

    /* ---------- flujos ---------- */

    protected function startFlow(Flow $flow): bool
    {
        $steps = (array) $flow->steps;
        if (!$steps) {
            return false;
        }

        $this->session->flow_id = $flow->id;
        $this->session->state = ['waiting' => null, 'answers' => []];
        if ($flow->intro) {
            $this->text($flow->intro);
        }
        $this->advance($steps[0]['key']);

        return true;
    }

    protected function flow(): ?Flow
    {
        return $this->session->flow_id ? Flow::find($this->session->flow_id) : null;
    }

    protected function stepByKey(?string $key): ?array
    {
        $flow = $this->flow();
        foreach ((array) $flow?->steps as $step) {
            if (($step['key'] ?? null) === $key) {
                return $step;
            }
        }

        return null;
    }

    protected function waitingStep(): ?array
    {
        $key = ((array) $this->session->state)['waiting'] ?? null;

        return $key ? $this->stepByKey($key) : null;
    }

    protected function resetFlow(): void
    {
        $this->session->flow_id = null;
        $this->session->state = ['waiting' => null, 'answers' => []];
    }

    protected function saveAnswer(array $step, string $label, string $value): void
    {
        $state = (array) $this->session->state;
        if (!empty($step['save_as'])) {
            $state['answers'][$step['save_as']] = $label;
            $state['answers'][$step['save_as'] . '_value'] = $value;
        }
        $state['waiting'] = null;
        $this->session->state = $state;
    }

    protected function choose(string $value): void
    {
        $step = $this->waitingStep();
        if (!$step || $step['type'] !== 'choice') {
            $this->text('Esa opción ya no aplica. Escribe tu duda o vuelve al menú.');
            $this->buttons([['Ver el menú', 'menu']]);

            return;
        }

        $option = collect((array) ($step['options'] ?? []))->first(fn ($o) => (string) ($o['value'] ?? '') === $value);
        if (!$option) {
            $this->text('No reconozco esa opción. Elige una de las que te mostré.');

            return;
        }

        $this->session->remember('u', (string) $option['label']);
        $this->saveAnswer($step, (string) $option['label'], $value);
        $this->advance($option['next'] ?? $step['next'] ?? null);
    }

    protected function interpolate(string $text): string
    {
        $answers = (array) (((array) $this->session->state)['answers'] ?? []);

        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', fn ($m) => (string) ($answers[$m[1]] ?? ''), $text);
    }

    /** Recorre pasos desde `$key` hasta uno que espera respuesta o hasta el final. */
    protected function advance(?string $key): void
    {
        $flow = $this->flow();
        $numbered = collect((array) $flow?->steps)->filter(fn ($s) => !empty($s['numbered']))->pluck('key')->values();
        $guard = 0;

        while ($key && $guard++ < self::MAX_STEPS_PER_TURN) {
            $step = $this->stepByKey($key);
            if (!$step) {
                break;
            }

            if (!empty($step['numbered']) && ($n = $numbered->search($step['key'])) !== false) {
                $this->add(['type' => 'step', 'n' => $n + 1, 'of' => $numbered->count(), 'title' => $this->interpolate((string) ($step['title'] ?? ''))]);
            }

            $body = $this->interpolate((string) ($step['body'] ?? ''));
            $next = $step['next'] ?? null;

            switch ($step['type']) {
                case 'message':
                    $body !== '' && $this->text($body);
                    break;

                case 'choice':
                    $body !== '' && $this->text($body);
                    $this->buttons(array_map(fn ($o) => [(string) $o['label'], 'opt:' . $o['value']], (array) $step['options']));
                    $this->setWaiting($step['key']);

                    return;

                case 'ask':
                    $body !== '' && $this->text($body);
                    $this->setWaiting($step['key']);

                    return;

                case 'guide':
                    $this->withCard($body, Cards::guideBySlug((string) ($step['ref'] ?? '')));
                    break;

                case 'doc':
                    $this->withCard($body, Cards::docBySlug((string) ($step['ref'] ?? '')));
                    break;

                case 'video':
                    $this->withCard($body, Cards::video($step['ref'] ?? $flow?->plugin));
                    break;

                case 'link':
                    $this->withCard($body, Cards::link($this->interpolate((string) ($step['label'] ?? '')), (string) ($step['ref'] ?? '')));
                    break;

                case 'done':
                    $body !== '' && $this->text($body);
                    $this->buttons([['Se resolvió', 'resolved'], ['Necesito más ayuda', 'unresolved']]);

                    return;
            }

            $key = $next;
        }

        // Llegó al final sin un paso «Final»: se cierra con la pregunta de siempre.
        $this->afterContent();
    }

    /** El texto que presenta una tarjeta solo se dice si la tarjeta existe (contenido publicado, video cargado…). */
    protected function withCard(string $body, ?array $card): void
    {
        if (!$card) {
            return;
        }

        $body !== '' && $this->text($body);
        $this->add($card);
    }

    protected function setWaiting(string $key): void
    {
        $state = (array) $this->session->state;
        $state['waiting'] = $key;
        $this->session->state = $state;
    }

    /* ---------- cierre y escalado ---------- */

    protected function resolve(): void
    {
        if ($this->session->status === 'open') {
            $this->session->status = 'resolved';
            $this->session->resolved_at = now();
            \Event::fire('aero.help.sessionResolved', [$this->session]);
        }

        $this->text('Me alegra haberte ayudado. Si te surge otra duda, aquí estoy.');
        $this->buttons([['Otra duda', 'menu']]);
    }

    protected function unresolved(): void
    {
        $this->text('Lamento que no alcanzara. Puedo pasar tu consulta a una persona con todo lo que hablamos.');
        $this->escalationOptions();
    }

    protected function escalationOptions(): void
    {
        $options = Escalation::available();
        if (!$options) {
            $this->text('Por ahora no hay un canal de soporte disponible desde aquí.');

            return;
        }

        $this->blocks[] = ['type' => 'escalate', 'options' => $options];
    }
}
