<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\ContributionService;
use Aero\Church\Classes\CurrentTenant;
use Aero\Church\Classes\ReceiptPdf;
use Aero\Church\Classes\ScopesToTenant;
use Aero\Church\Models\Contribution;
use Aero\Church\Models\Fund;
use Backend\Classes\Controller;
use BackendMenu;

class Contributions extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.treasury', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'contributions');
    }

    protected function ensureDefaultFunds(): void
    {
        $id = CurrentTenant::isAdmin() ? null : CurrentTenant::id();
        if ($id && !Fund::where('tenant_id', $id)->exists()) {
            Fund::seedDefaults($id);
        }
    }

    public function index()
    {
        $this->ensureDefaultFunds();

        return $this->asExtension('ListController')->index();
    }

    public function create($context = null)
    {
        $this->ensureDefaultFunds();

        return $this->asExtension('FormController')->create($context);
    }

    /** Aporte por QR: al crearlo se intenta emitir el QR; si falta la cuenta, queda pendiente y se avisa. */
    public function formAfterCreate($model): void
    {
        if ($model->method !== 'qr') {
            return;
        }
        try {
            app(ContributionService::class)->issueQr($model);
        } catch (ChurchException $e) {
            \Flash::warning($e->getMessage());
        }
    }

    protected function findContribution($id): Contribution
    {
        return Contribution::visible()->findOrFail((int) $id);
    }

    protected function attempt(callable $fn, string $ok)
    {
        try {
            $fn();
        } catch (ChurchException $e) {
            throw new \ApplicationException($e->getMessage());
        }
        \Flash::success($ok);

        return \Redirect::refresh();
    }

    public function update_onIssueQr($recordId = null)
    {
        $c = $this->findContribution($recordId);

        return $this->attempt(fn () => app(ContributionService::class)->issueQr($c), 'QR generado.');
    }

    /** Para un pago que el banco confirmó fuera del flujo automático (p. ej. se verificó en el extracto). */
    public function update_onConfirmManual($recordId = null)
    {
        $c = $this->findContribution($recordId);

        return $this->attempt(fn () => $c->markConfirmed() ?: throw new ChurchException('Solo un aporte pendiente se puede confirmar.'), 'Aporte confirmado.');
    }

    public function update_onCancelContribution($recordId = null)
    {
        $c = $this->findContribution($recordId);

        return $this->attempt(fn () => $c->markCancelled(), 'Aporte anulado.');
    }

    public function update_onIssueReceipt($recordId = null)
    {
        $c = $this->findContribution($recordId);

        return $this->attempt(fn () => app(ContributionService::class)->receiptNumber($c), 'Comprobante emitido.');
    }

    /** Descarga del comprobante (PDF; si no hay dompdf, HTML imprimible). */
    public function receipt($id = null)
    {
        $c = $this->findContribution($id);
        try {
            app(ContributionService::class)->receiptNumber($c);
        } catch (ChurchException $e) {
            return response(e($e->getMessage()), 422);
        }
        $c = $c->fresh(['fund', 'member']);

        $pdf = new ReceiptPdf();
        if ($bytes = $pdf->pdf($c)) {
            return response($bytes, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="comprobante-' . $c->receipt_number . '.pdf"',
            ]);
        }

        return response($pdf->html($c), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
