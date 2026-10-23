<?php namespace Aero\Church\Components;

use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\EventService;
use Aero\Church\Classes\PortalAccess;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\ChurchSettings;
use Aero\Church\Models\EventRegistration;
use Aero\Church\Models\Member;
use Aero\Church\Models\Post;
use Aero\Church\Models\Resource;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;

/**
 * Portal privado del miembro (`/miembro`): sus datos, grupos, eventos y el contenido solo para miembros.
 * El miembro es el usuario de RainLab.User vinculado a un Member DE ESTA IGLESIA; el tenant sale del host.
 * El miembro solo edita contacto y preferencias de mensajes; nombre, estado y familia los gestiona la secretaría.
 */
class ChurchPortal extends ComponentBase
{
    use \Aero\Church\Classes\FallsThrough;

    public ?Tenant $tenant = null;
    public $user = null;
    public ?Member $member = null;
    public array $groups = [];
    public $events = null;
    public array $mine = [];
    public $posts = null;
    public $resources = null;

    public function componentDetails(): array
    {
        return ['name' => 'Iglesia (portal del miembro)', 'description' => 'Datos, grupos, eventos y contenido para miembros.'];
    }

    public function onRun()
    {
        if (!$this->boot()) {
            return $this->fallThrough();
        }
        $this->load();
        $this->page['churchPortal'] = $this;
    }

    protected function boot(): bool
    {
        $this->tenant = $this->tenant ?: Tenant::resolveFromDomain(request()->getHost());
        if (!$this->tenant) {
            return false;
        }
        $s = ChurchSettings::where('tenant_id', $this->tenant->id)->first();
        if (!$s || !$s->portal_enabled) {
            return false;
        }
        $this->user = \Auth::user();
        $this->member = PortalAccess::memberFor($this->user, $this->tenant->id);

        return true;
    }

    public function churchName(): string
    {
        return (string) $this->tenant?->name;
    }

    protected function load(): void
    {
        $m = $this->member;
        if (!$m) {
            return;
        }

        $this->groups = $m->groupMemberships()->whereNull('left_at')->with('group')->get()
            ->filter(fn ($gm) => $gm->group && $gm->group->is_active)->map(fn ($gm) => [
                'name' => $gm->group->name, 'role' => \Aero\Church\Models\GroupMember::ROLES[$gm->role] ?? $gm->role,
                'when' => trim(($gm->group->meeting_day ? (\Aero\Church\Models\Group::DAYS[$gm->group->meeting_day] ?? '') : '') . ' ' . $gm->group->meeting_time),
                'where' => $gm->group->location,
            ])->values()->all();

        $this->events = ChurchEvent::forTenant($this->tenant->id)->upcoming()->orderBy('starts_at')->limit(20)->get();
        $this->mine = EventRegistration::where('member_id', $m->id)->whereIn('status', ['registered', 'waitlist'])->get()->keyBy('event_id')->all();
        $this->posts = Post::forTenant($this->tenant->id)->shown(true)->where('visibility', 'members')->orderByDesc('published_at')->limit(10)->get();
        $this->resources = Resource::forTenant($this->tenant->id)->shown(true)->where('visibility', 'members')->orderBy('title')->get();
    }

    protected function requireMember(): Member
    {
        if (!$this->boot() || !$this->member) {
            throw new \ApplicationException('Necesitas ingresar con tu cuenta de miembro.');
        }

        return $this->member;
    }

    public function onSaveProfile()
    {
        $m = $this->requireMember();

        $email = trim((string) post('email'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \ApplicationException('El correo no es válido.');
        }

        // Lista blanca: nada de estado, sede, familia, tutor ni consentimiento de datos.
        $m->phone = post('phone') ?: null;
        $m->email = $email ?: null;
        $m->address = mb_substr((string) post('address'), 0, 255) ?: null;
        $m->consent_messages = (bool) post('consent_messages');
        $m->save();

        \Flash::success('Tus datos se actualizaron.');

        return \Redirect::refresh();
    }

    public function onRegisterEvent()
    {
        $m = $this->requireMember();
        $event = ChurchEvent::forTenant($this->tenant->id)->find((int) post('event_id'));
        if (!$event) {
            throw new \ApplicationException('El evento ya no está disponible.');
        }
        try {
            $reg = app(EventService::class)->register($event, $m);
        } catch (ChurchException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success($reg->status === 'registered' ? 'Quedaste inscrito.' : 'El cupo está lleno: quedaste en lista de espera.');

        return \Redirect::refresh();
    }

    public function onCancelEvent()
    {
        $m = $this->requireMember();
        $reg = EventRegistration::where('member_id', $m->id)->where('event_id', (int) post('event_id'))->whereIn('status', ['registered', 'waitlist'])->first();
        if (!$reg) {
            throw new \ApplicationException('No tienes inscripción en ese evento.');
        }
        app(EventService::class)->cancel($reg);
        \Flash::success('Inscripción cancelada.');

        return \Redirect::refresh();
    }
}
