<?php namespace Aero\Crm\Models\Contact;

use Aero\Crm\Models\Company;
use Aero\Crm\Models\Contact;
use Aero\Sites\Models\TenantImportModel;
use ApplicationException;

class ImportModel extends TenantImportModel
{
    /**
     * importRowForTenant crea el contacto o, si ya existe uno con el mismo
     * email en el tenant, actualiza sus datos.
     */
    protected function importRowForTenant(array $row, int $tenantId): bool
    {
        $email     = trim((string) ($row['email'] ?? ''));
        $firstName = trim((string) ($row['first_name'] ?? ''));
        $lastName  = trim((string) ($row['last_name'] ?? ''));

        if ($email === '' && $firstName === '' && $lastName === '') {
            throw new ApplicationException('Fila vacía: falta nombre, apellido o email.');
        }

        $contact = $email !== ''
            ? Contact::where('tenant_id', $tenantId)->where('email', $email)->first()
            : null;

        $wasCreated = !$contact;
        $contact = $contact ?: new Contact;
        $contact->tenant_id = $tenantId;

        if ($firstName !== '') $contact->first_name = $firstName;
        if ($lastName !== '') $contact->last_name = $lastName;
        if ($email !== '') $contact->email = $email;

        if (!empty($row['phone'])) {
            $contact->phone = trim((string) $row['phone']);
        }

        if (!empty($row['source'])) {
            $contact->source = trim((string) $row['source']);
        }

        if (!empty($row['company'])) {
            $company = Company::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => trim((string) $row['company'])]
            );
            $contact->company_id = $company->id;
        }

        $contact->save();

        return $wasCreated;
    }
}
