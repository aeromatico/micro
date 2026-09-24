<?php namespace Aero\Sms\Controllers;

use Aero\Sms\Classes\Billing;
use Aero\Sms\Classes\CurrentTenant;
use Aero\Sms\Classes\RecipientParser;
use Aero\Sms\Classes\Segments;
use Aero\Sms\Classes\Sms;
use Aero\Sms\Models\Settings;
use Aero\Sms\Models\Template;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;
use InvalidArgumentException;

/** Envío manual desde el panel: un mensaje o un lote pegando números o subiendo un CSV. */
class Compose extends Controller
{
    public $requiredPermissions = ['aero.sms.use', 'aero.sms.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sms', 'sms', 'compose');
        $this->pageTitle = 'Enviar SMS';
    }

    public function index(): void
    {
        $tenantId = CurrentTenant::isAdmin() ? null : CurrentTenant::id();

        $this->vars['isAdmin'] = CurrentTenant::isAdmin();
        $this->vars['templates'] = Template::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($q) => $q->orWhere('tenant_id', $tenantId)))
            ->orderBy('name')->get();
        $this->vars['tenants'] = $this->tenantOptions();
        $this->vars['tenantId'] = $tenantId;
        $this->vars['driver'] = Settings::driverCode();
        $this->vars['maxBatch'] = Settings::maxBatchSize();
    }

    public function onQuote()
    {
        try {
            [$data, $recipients] = $this->collect();
        }
        catch (InvalidArgumentException $e) {
            return ['#quote' => '<div class="text-danger">' . e($e->getMessage()) . '</div>'];
        }

        $sample = $recipients[0]['vars'] ?? [];
        $body = !empty($data['template'])
            ? Template::render(Template::findForTenant($data['template'], $data['tenant_id'])?->body ?? '', $sample)
            : (string) $data['body'];
        $count = Segments::count($body);
        $n = count($recipients);
        $perSms = Billing::quote($count['segments']);

        return ['#quote' => sprintf(
            '<div class="callout callout-info"><div class="content">%d destinatario(s) · %d segmento(s) por SMS (%s, %d caracteres) · <strong>%d créditos</strong>%s</div></div>',
            $n,
            $count['segments'],
            $count['encoding'],
            $count['length'],
            $perSms * $n,
            $data['tenant_id'] ? '' : ' (se enviará como Plataforma: no se cobra)'
        )];
    }

    public function onSend()
    {
        try {
            [$data, $recipients] = $this->collect();
            $consumer = [
                'tenant_id'  => $data['tenant_id'],
                'api_key_id' => null,
                'label'      => 'Panel: ' . (BackendAuth::getUser()->email ?? 'admin'),
            ];

            if (count($recipients) === 1) {
                $message = Sms::send([
                    'to'           => $recipients[0]['to'],
                    'body'         => $data['body'],
                    'template'     => $data['template'],
                    'vars'         => $recipients[0]['vars'],
                    'scheduled_at' => $data['scheduled_at'],
                ], $consumer);

                Flash::success('Mensaje en cola.');

                return \Backend::redirect('aero/sms/messages/preview/' . $message->id);
            }

            $batch = Sms::sendBatch([
                'name'         => $data['name'] ?: 'Envío desde el panel',
                'body'         => $data['body'],
                'template'     => $data['template'],
                'recipients'   => $recipients,
                'scheduled_at' => $data['scheduled_at'],
            ], $consumer);

            Flash::success("Lote creado con {$batch->total} destinatarios.");

            return \Backend::redirect('aero/sms/batches/preview/' . $batch->id);
        }
        catch (InvalidArgumentException $e) {
            Flash::error($e->getMessage());
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            Flash::error($e->getMessage() . ' No se envió nada.');
        }
    }

    /** @return array{0: array, 1: array} */
    protected function collect(): array
    {
        $text = (string) input('recipients');

        if ($file = request()->file('csv')) {
            $text .= "\n" . file_get_contents($file->getRealPath());
        }

        $recipients = RecipientParser::parse($text);

        if (!$recipients) {
            throw new InvalidArgumentException('Agrega al menos un destinatario.');
        }

        if (count($recipients) > Settings::maxBatchSize()) {
            throw new InvalidArgumentException('Máximo ' . Settings::maxBatchSize() . ' destinatarios por envío.');
        }

        $template = input('template') ?: null;
        $body = trim((string) input('body'));

        if (!$template && $body === '') {
            throw new InvalidArgumentException('Escribe el mensaje o elige una plantilla.');
        }

        return [[
            'name'         => trim((string) input('name')),
            'body'         => $body,
            'template'     => $template,
            'tenant_id'    => $this->targetTenantId(),
            'scheduled_at' => input('scheduled_at') ?: null,
        ], $recipients];
    }

    /** El tenant no elige a quién cobrar: siempre es él. Solo el superadmin escoge. */
    protected function targetTenantId(): ?int
    {
        if (CurrentTenant::isAdmin()) {
            return input('tenant_id') ? (int) input('tenant_id') : null;
        }

        $id = CurrentTenant::id();

        if (!$id) {
            throw new InvalidArgumentException('Tu usuario no está asociado a ningún tenant.');
        }

        return $id;
    }

    protected function tenantOptions(): array
    {
        if (!class_exists(\Aero\Sites\Models\Tenant::class)) {
            return [];
        }

        return \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all();
    }
}
