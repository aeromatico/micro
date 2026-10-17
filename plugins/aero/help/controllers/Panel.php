<?php namespace Aero\Help\Controllers;

use Aero\Help\Classes\Engine;
use Aero\Help\Classes\Escalation;
use Backend\Classes\Controller;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Solo expone los handlers AJAX del panel de ayuda; sin menú ni vistas.
 */
class Panel extends Controller
{
    public $requiredPermissions = ['aero.help.use', 'aero.help.superadmin'];

    public function onStart(): array
    {
        $this->throttle('start', 30);

        return Engine::start((string) post('url'), (string) post('area'));
    }

    public function onSend(): array
    {
        $this->throttle('send', 240);

        $engine = Engine::find((int) post('session'));
        if (!$engine) {
            throw new \ApplicationException('La conversación expiró. Abre la ayuda de nuevo.');
        }

        $action = post('action') === 'text' ? 'text' : 'choose';

        return $engine->respond($action, mb_substr((string) post('value'), 0, 500));
    }

    public function onEscalate(): array
    {
        $engine = Engine::find((int) post('session'));
        if (!$engine) {
            throw new \ApplicationException('La conversación expiró. Abre la ayuda de nuevo.');
        }

        $result = Escalation::run($engine->session(), (string) post('via'), mb_substr((string) post('note'), 0, 1000));

        return ['ok' => true] + $result;
    }

    public function index(): \Illuminate\Http\Response
    {
        return response('', 204);
    }

    protected function throttle(string $name, int $perHour): void
    {
        $key = "help-{$name}:" . $this->user->id;
        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            throw new \ApplicationException('Demasiadas solicitudes seguidas. Espera unos minutos.');
        }
        RateLimiter::hit($key, 3600);
    }
}
