<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\DocumentRenderer;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Formato de documento del negocio (certificado, constancia, informe, consentimiento) con variables. */
class DocumentTemplate extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_document_templates';

    public $fillable = ['tenant_id', 'name', 'kind', 'body', 'is_active'];

    public $casts = ['is_active' => 'boolean'];

    public $rules = [
        'name' => 'required|string|max:255',
        'kind' => 'required|in:certificate,constancia,report,consent,other',
        'body' => 'required|string|max:20000',
    ];

    public const KINDS = ['certificate' => 'Certificado', 'constancia' => 'Constancia', 'report' => 'Informe', 'consent' => 'Consentimiento', 'other' => 'Otro'];

    public function getKindOptions(): array
    {
        return self::KINDS;
    }

    public function beforeValidate(): void
    {
        if ($unknown = DocumentRenderer::unknownVariables((string) $this->body)) {
            throw new \ApplicationException('Variables desconocidas en el formato: {{' . implode('}}, {{', $unknown) . '}}. Disponibles: ' . implode(', ', array_keys(DocumentRenderer::VARIABLES)) . '.');
        }
    }
}
