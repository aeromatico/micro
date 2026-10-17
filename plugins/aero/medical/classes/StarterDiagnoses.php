<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Cie10;
use Aero\Medical\Models\DiagnosisCatalog;

/**
 * Lista inicial de diagnósticos frecuentes de medicina general (atención primaria) para personas. Son SOLO
 * códigos: el título sale del catálogo CIE-10 cargado (nunca escrito aquí) y un código que no esté en el
 * catálogo se omite y se informa. Es una selección de partida, NO revisada por un médico: el negocio la
 * ajusta (quita, agrega, fija) como cualquier otra lista de Frecuentes.
 */
class StarterDiagnoses
{
    public const CODES = [
        // Cardiovascular y metabólico
        'I10', 'E11.9', 'E78.5', 'E66.9',
        // Respiratorio
        'J00', 'J02.9', 'J03.9', 'J06.9', 'J20.9', 'J18.9', 'J45.9', 'R05',
        // Digestivo
        'K29.7', 'K21.9', 'K30', 'K59.0', 'A09',
        // Genitourinario
        'N39.0', 'N76.0',
        // Musculoesquelético y dolor
        'M54.5', 'M25.5', 'M79.1', 'R51', 'G43.9',
        // Piel, ojos y oídos
        'L20.9', 'L30.9', 'H10.9', 'H66.9',
        // Sangre y salud mental
        'D50.9', 'F41.9', 'F32.9',
        // Síntomas generales
        'R50.9', 'R10.4', 'R11', 'R42', 'R53',
        // Atención preventiva
        'Z00.0', 'Z00.1', 'Z23',
    ];

    /** Agrega al catálogo del negocio los que existan en CIE-10 y aún no tenga. @return array{created:int, skipped:int, missing:array} */
    public static function load(int $tenantId): array
    {
        if (Mode::forTenant($tenantId) !== Mode::HUMAN) {
            throw new MedicalException('La lista de medicina general es para personas: el negocio debe estar en modo humano.');
        }
        if (!Cie10::exists()) {
            throw new MedicalException('El catálogo CIE-10 no está cargado (php artisan medical:import-cie10).');
        }

        $found = Cie10::whereIn('code', self::CODES)->get()->keyBy('code');
        $created = $skipped = 0;
        $missing = [];

        foreach (self::CODES as $code) {
            $row = $found[$code] ?? null;
            if (!$row) {
                $missing[] = $code;
                continue;
            }
            if (DiagnosisCatalog::where('tenant_id', $tenantId)->where('source', 'cie10')->where('entry_key', $code)->exists()) {
                $skipped++;
                continue;
            }
            DiagnosisCatalog::create(['tenant_id' => $tenantId, 'source' => 'cie10', 'code' => $row->code, 'label' => $row->title, 'pinned' => false]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'missing' => $missing];
    }
}
