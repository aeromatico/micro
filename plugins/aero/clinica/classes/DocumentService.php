<?php namespace Aero\Clinica\Classes;

use Aero\Clinica\Models\Consultation;
use Aero\Clinica\Models\DocumentTemplate;
use Aero\Clinica\Models\ClinicaDocument;
use Aero\Clinica\Models\Patient;
use Aero\Clinica\Models\Professional;

/** Generar (borrador editable), emitir (firma e inmutabilidad) y descartar documentos del paciente. */
class DocumentService
{
    public function generate(Patient $p, DocumentTemplate $t, $user, ?Consultation $c = null): ClinicaDocument
    {
        if ((int) $t->tenant_id !== (int) $p->tenant_id || !$t->is_active) {
            throw new ClinicaException('Formato no disponible.');
        }
        if ($c && ((int) $c->patient_id !== (int) $p->id || (int) $c->tenant_id !== (int) $p->tenant_id)) {
            throw new ClinicaException('La nota no corresponde a este paciente.');
        }

        $profile = Professional::forUser((int) $p->tenant_id, $user);

        return ClinicaDocument::create([
            'tenant_id' => $p->tenant_id, 'patient_id' => $p->id, 'consultation_id' => $c?->id, 'template_id' => $t->id,
            'kind' => $t->kind, 'title' => $t->name, 'body' => DocumentRenderer::render($t->body, $p, $user, $c),
            'user_id' => $user->id, 'professional_name' => $profile->full_name,
        ]);
    }

    public function update(ClinicaDocument $d, string $title, string $body, $user): ClinicaDocument
    {
        $this->assertDraft($d, $user);
        $title = trim($title);
        if ($title === '' || trim($body) === '') {
            throw new ClinicaException('El título y el texto no pueden quedar vacíos.');
        }
        $d->title = mb_substr($title, 0, 255);
        $d->body = mb_substr($body, 0, 20000);
        $d->save();

        return $d;
    }

    public function issue(ClinicaDocument $d, $user): ClinicaDocument
    {
        $this->assertDraft($d, $user);
        if (str_contains((string) $d->body, DocumentRenderer::BLANK) && !str_contains(mb_strtolower((string) $d->kind), 'consent')) {
            // Una línea en blanco es válida en un consentimiento (se completa a mano); en otros documentos avisa.
            throw new ClinicaException('El documento tiene datos sin completar (' . DocumentRenderer::BLANK . '). Complételos o bórrelos antes de emitir.');
        }

        $d->professional_name = Professional::forUser((int) $d->tenant_id, $user)->full_name;
        $d->status = 'issued';
        $d->issued_at = now();
        $d->content_hash = $d->computeHash();
        $d->save();

        Audit::record('sign', $d->tenant_id, $d->patient_id, 'document', $d->id);
        // Solo ids: Aero.Api reenvía a webhooks lo que viaja en eventos aero.*.
        \Event::fire('aero.clinica.documentGenerated', [['document_id' => $d->id, 'patient_id' => $d->patient_id, 'tenant_id' => $d->tenant_id]]);

        return $d;
    }

    public function discard(ClinicaDocument $d, $user): void
    {
        $this->assertDraft($d, $user);
        $d->delete();
    }

    protected function assertDraft(ClinicaDocument $d, $user): void
    {
        if ($d->isIssued()) {
            throw new ClinicaException('El documento ya fue emitido y no se puede modificar. Genere uno nuevo.');
        }
        if ((int) $d->user_id !== (int) $user->id) {
            throw new ClinicaException('Solo quien generó el documento puede editarlo y emitirlo.');
        }
    }
}
