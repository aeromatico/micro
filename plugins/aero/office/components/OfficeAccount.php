<?php namespace Aero\Office\Components;

use Aero\Office\Classes\BookingService;
use Aero\Office\Classes\OfficeException;
use Aero\Office\Models\Booking;
use Aero\Office\Models\Customer;
use Aero\Office\Models\OfficeSettings;
use Aero\Sites\Models\Tenant;
use Cms\Classes\ComponentBase;

/**
 * «Mis reservas»: el cliente (usuario de rainlab:user) ve sus próximas reservas y su
 * historial EN EL NEGOCIO DEL DOMINIO. El vínculo es Customer.user_id dentro del tenant
 * del host; otro negocio con el mismo usuario no se mezcla (falla cerrado).
 */
class OfficeAccount extends ComponentBase
{
    public ?Tenant $tenant = null;
    public $user = null;
    public array $upcoming = [];
    public array $history = [];

    public function componentDetails(): array
    {
        return ['name' => 'Reservas (mi cuenta)', 'description' => 'Próximas reservas e historial del cliente.'];
    }

    public function onRun()
    {
        if (!$this->boot()) {
            return $this->controller->run('404');
        }
        $this->load();
    }

    protected function boot(): bool
    {
        $this->tenant = $this->tenant ?: Tenant::resolveFromDomain(request()->getHost());
        $this->user = \Auth::getUser();

        return $this->tenant && OfficeSettings::isPublic($this->tenant->id);
    }

    protected function customerIds(): array
    {
        return $this->user
            ? Customer::where('tenant_id', $this->tenant->id)->where('user_id', $this->user->id)->pluck('id')->all()
            : [];
    }

    protected function load(): void
    {
        $ids = $this->customerIds();
        if (!$ids) {
            return;
        }

        $rows = Booking::where('tenant_id', $this->tenant->id)->whereIn('customer_id', $ids)
            ->with(['branch', 'worker'])->orderByDesc('starts_at')->limit(200)->get();

        $labels = Booking::statuses();
        $map = fn (Booking $b) => [
            'id'       => $b->id,
            'code'     => $b->code,
            'service'  => $b->service_name,
            'worker'   => $b->worker?->name ?: $b->worker_name,
            'branch'   => $b->branch?->name,
            'when'     => $b->starts_at->locale('es')->translatedFormat('D d/m/Y · H:i'),
            'status'   => $b->status,
            'label'    => $labels[$b->status] ?? $b->status,
            'price'    => (float) $b->price,
            'currency' => $b->currency,
            'url'      => '/reservas/' . $b->manage_token,
            'open'     => in_array($b->status, ['pending', 'confirmed'], true) && $b->starts_at->isFuture(),
        ];

        $this->upcoming = $rows->filter(fn ($b) => in_array($b->status, ['pending', 'confirmed'], true) && $b->starts_at->isFuture())
            ->sortBy('starts_at')->map($map)->values()->all();
        $this->history = $rows->reject(fn ($b) => in_array($b->status, ['pending', 'confirmed'], true) && $b->starts_at->isFuture())
            ->map($map)->values()->all();
    }

    public function onCancelMine()
    {
        if (!$this->boot() || !$this->user) {
            throw new \ApplicationException('Ingresa a tu cuenta.');
        }

        // Solo una reserva de un cliente vinculado a ESTE usuario en ESTE negocio.
        $b = Booking::where('tenant_id', $this->tenant->id)->whereIn('customer_id', $this->customerIds())->find((int) post('booking_id'));
        if (!$b) {
            throw new \ApplicationException('Reserva no encontrada.');
        }

        try {
            app(BookingService::class)->cancel($b, 'Cancelada por el cliente', 'customer');
        } catch (OfficeException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        \Flash::success('Tu cita fue cancelada.');

        return \Redirect::to('/cuenta/reservas');
    }
}
