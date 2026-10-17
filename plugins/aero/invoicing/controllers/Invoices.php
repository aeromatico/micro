<?php namespace Aero\Invoicing\Controllers;

use Aero\Invoicing\Classes\InvoicePdf;
use Aero\Invoicing\Classes\InvoiceService;
use Aero\Invoicing\Classes\ScopesToTenant;
use Aero\Invoicing\Models\Invoice;
use Backend\Classes\Controller;
use BackendMenu;
use Flash;

class Invoices extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.invoicing.use', 'aero.invoicing.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Invoicing', 'invoicing', 'invoices');
    }

    /** Una factura ya emitida es un documento fiscal: se muestra, no se edita. */
    public function formExtendFields($form): void
    {
        if ($form->model->exists && !$form->model->isEditable) {
            foreach ($form->getFields() as $field) {
                $field->readOnly = true;
            }
        }
    }

    public function onIssue()
    {
        $invoice = $this->findScoped((int) post('id'));

        try {
            $invoice = InvoiceService::issue($invoice);
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());

            return;
        }

        match ($invoice->status) {
            'validated', 'received' => Flash::success("Factura emitida (N.º {$invoice->number})."),
            'rejected' => Flash::error('El SIN rechazó la factura: ' . implode(' ', (array) $invoice->messages)),
            default => Flash::error('No se pudo emitir: ' . $invoice->error),
        };

        return \Backend::redirect('aero/invoicing/invoices/update/' . $invoice->id);
    }

    public function onVoid()
    {
        $invoice = $this->findScoped((int) post('id'));

        try {
            InvoiceService::void($invoice, (int) (post('reason') ?: 1));
            Flash::success('Factura anulada.');
        } catch (\DomainException $e) {
            Flash::error($e->getMessage());

            return;
        }

        return \Backend::redirect('aero/invoicing/invoices/update/' . $invoice->id);
    }

    /** Representación gráfica en PDF (si no hay dompdf, versión HTML imprimible). Por el scope del tenant. */
    public function pdf($id = null)
    {
        $invoice = $this->findScoped((int) $id);
        $renderer = new InvoicePdf();
        $pdf = $renderer->pdf($invoice);

        if ($pdf === null) {
            return response($renderer->html($invoice), 200, ['Content-Type' => 'text/html; charset=utf-8']);
        }

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . InvoicePdf::filename($invoice) . '"',
        ]);
    }

    /** Siempre por el scope del tenant: un id ajeno responde 404, no una factura ajena. */
    protected function findScoped(int $id): Invoice
    {
        return $this->scopeToTenant(Invoice::query())->findOrFail($id);
    }
}
