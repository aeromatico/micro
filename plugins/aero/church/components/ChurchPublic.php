<?php namespace Aero\Church\Components;

use Aero\Church\Classes\CareService;
use Aero\Church\Classes\ChurchException;
use Aero\Church\Models\ChurchSettings;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Formularios públicos de la iglesia del dominio: bienvenida (visitantes) y oración. El tenant sale SIEMPRE
 * del host. Cada formulario está apagado hasta que la iglesia lo activa en su configuración.
 * Propiedad `mode` de la página: welcome | prayer.
 */
class ChurchPublic extends ComponentBase
{
    use \Aero\Church\Classes\FallsThrough;

    public ?Tenant $tenant = null;
    public ?ChurchSettings $settings = null;
    public string $mode = 'welcome';

    public function componentDetails(): array
    {
        return ['name' => 'Iglesia (formularios públicos)', 'description' => 'Bienvenida de visitantes y peticiones de oración.'];
    }

    public function defineProperties(): array
    {
        return ['mode' => ['title' => 'Formulario', 'type' => 'dropdown', 'default' => 'welcome', 'options' => ['welcome' => 'Bienvenida', 'prayer' => 'Oración']]];
    }

    public function onRun()
    {
        $this->mode = (string) $this->property('mode') === 'prayer' ? 'prayer' : 'welcome';
        if (!$this->boot()) {
            return $this->fallThrough();
        }
        $this->page['churchForm'] = $this;
    }

    protected function boot(): bool
    {
        $this->tenant = $this->tenant ?: Tenant::resolveFromDomain(request()->getHost());
        if (!$this->tenant) {
            return false;
        }
        $this->settings = ChurchSettings::where('tenant_id', $this->tenant->id)->first();

        $flag = $this->mode === 'prayer' ? 'prayer_enabled' : 'welcome_enabled';

        return (bool) ($this->settings && $this->settings->{$flag});
    }

    public function churchName(): string
    {
        return (string) $this->tenant?->name;
    }

    protected function guard(string $mode, string $bucket): void
    {
        $this->mode = $mode;
        if (!$this->boot()) {
            throw new \ApplicationException('Este formulario no está disponible.');
        }
        if (post('website')) { // honeypot
            throw new \ApplicationException('No se pudo enviar.');
        }
        $key = 'church-' . $bucket . ':' . $this->tenant->id . ':' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw new \ApplicationException('Demasiados envíos. Prueba de nuevo en un rato.');
        }
        RateLimiter::hit($key, 3600);
    }

    public function onSubmitWelcome()
    {
        $this->guard('welcome', 'welcome');
        try {
            app(CareService::class)->registerVisit($this->tenant->id, [
                'name' => post('name'), 'phone' => post('phone'), 'email' => post('email'), 'how_heard' => post('how_heard'),
                'interest' => post('interest'), 'consent_data' => post('consent_data'), 'consent_messages' => post('consent_messages'),
            ]);
        } catch (ChurchException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return ['#church-form' => $this->renderPartial('@thanks', ['kind' => 'welcome'])];
    }

    public function onSubmitPrayer()
    {
        $this->guard('prayer', 'prayer');
        try {
            app(CareService::class)->registerPrayer($this->tenant->id, [
                'name' => post('name'), 'phone' => post('phone'), 'request' => post('request'), 'is_private' => post('is_private'),
                'wants_contact' => post('wants_contact'), 'consent_data' => post('consent_data'),
            ]);
        } catch (ChurchException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return ['#church-form' => $this->renderPartial('@thanks', ['kind' => 'prayer'])];
    }
}
