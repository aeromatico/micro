<?php namespace Aero\Sites\Models;

use ApplicationException;
use Backend\Models\ImportModel;
use Throwable;

/**
 * TenantImportModel es la base reutilizable para todos los imports
 * multi-tenant del proyecto (CRM, Shop, QRBO, etc.).
 *
 * Se encarga de:
 *   - resolver el tenant actual y rechazar el import si no hay uno,
 *   - soportar CSV (con delimitador configurable), Excel y "Copiar y Pegar"
 *     vía Aero\Sites\Traits\VersatileImportModel,
 *   - loguear creado/actualizado/error por fila de forma consistente.
 *
 * Un import concreto solo necesita extender esta clase e implementar
 * importRowForTenant(). Ver docs/workflows.md — Flujo "Importación
 * versátil (CSV/Excel/Pegar)".
 */
abstract class TenantImportModel extends ImportModel
{
    use \Aero\Sites\Traits\VersatileImportModel;
    use \Aero\Sites\Traits\ResolvesCurrentTenant;

    public $rules = [];

    /**
     * importRowForTenant crea o actualiza un registro a partir de una fila
     * ya mapeada a nombres de columna de BD (según lo que el usuario
     * emparejó en el paso 2). Debe lanzar una excepción con un mensaje
     * legible si la fila es inválida, y devolver true si creó un registro
     * nuevo o false si actualizó uno existente.
     */
    abstract protected function importRowForTenant(array $row, int $tenantId): bool;

    /**
     * importData es llamado por el pipeline nativo de October una vez que
     * ya parseó el CSV/Excel/pegado y armó el array de filas.
     */
    public function importData($results, $sessionKey = null)
    {
        $tenant = $this->getCurrentTenant();

        if (!$tenant) {
            throw new ApplicationException('No se encontró un tenant para tu usuario; no se puede importar.');
        }

        foreach ($results as $index => $row) {
            $rowNumber = $index + 1;

            try {
                $wasCreated = $this->importRowForTenant($row, $tenant->id);
                $wasCreated ? $this->logCreated() : $this->logUpdated();
            }
            catch (Throwable $ex) {
                $this->logError($rowNumber, $ex->getMessage());
            }
        }
    }
}
