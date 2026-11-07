<?php namespace Aero\Clinica\Classes;

/**
 * Lo que cuelga de una nota (diagnósticos, receta, órdenes, cuestionarios)
 * se bloquea cuando la nota se firma: no se crea, edita ni borra nada.
 */
trait GuardsSignedNote
{
    protected function assertNoteOpen(?int $consultationId): void
    {
        if ($consultationId && \Aero\Clinica\Models\Consultation::where('id', $consultationId)->where('status', 'signed')->exists()) {
            throw new \ApplicationException('La nota está firmada: no se puede modificar. Use una adenda.');
        }
    }
}
