<?php namespace Aero\Viral\Components;

use Aero\Viral\Classes\ApplicationService;
use Aero\Viral\Classes\Catalog;
use Aero\Viral\Models\Application;
use Auth;
use Cms\Classes\ComponentBase;

/** Página /aplicar de viral.: formulario de postulación y estado de la persona. */
class ViralApply extends ComponentBase
{
    public function componentDetails(): array
    {
        return ['name' => 'Viral · aplicar', 'description' => 'Formulario de postulación de creadores y pantalla de estado.'];
    }

    public function onRun(): void
    {
        $this->page['viralBoot'] = $this->boot();
    }

    public function onSubmit(): array
    {
        $app = (new ApplicationService)->submit(post(), request()->ip());

        return ['application' => ApplicationService::summary($app), 'user' => $this->userData()];
    }

    public function onStatus(): array
    {
        return ['application' => ApplicationService::summary($this->currentApplication())];
    }

    protected function currentApplication(): ?Application
    {
        $user = Auth::user();

        return $user ? Application::where('user_id', $user->id)->latest('id')->first() : null;
    }

    protected function userData(): ?array
    {
        $user = Auth::user();

        return $user ? ['name' => trim($user->first_name . ' ' . $user->last_name), 'email' => $user->email] : null;
    }

    protected function boot(): array
    {
        return [
            'csrf' => csrf_token(),
            'catalog' => Catalog::forFrontend(),
            'user' => $this->userData(),
            'application' => ApplicationService::summary($this->currentApplication()),
        ];
    }
}
