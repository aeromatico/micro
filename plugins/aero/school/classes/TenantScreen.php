<?php namespace Aero\School\Classes;

/** Para las pantallas a medida (no CRUD): resuelve el colegio actual o avisa con claridad. */
trait TenantScreen
{
    protected function tenantId(): int
    {
        $id = CurrentTenant::id();
        if (!$id) {
            throw new \ApplicationException('No se pudo determinar su colegio. Seleccione el sitio del colegio y vuelva a intentar.');
        }

        return $id;
    }

    protected function courses()
    {
        return \Aero\School\Models\Course::where('tenant_id', $this->tenantId())->where('is_active', true)->orderBy('level')->orderBy('grade')->orderBy('name')->get();
    }

    protected function findStudent($id): \Aero\School\Models\Student
    {
        return \Aero\School\Models\Student::where('tenant_id', $this->tenantId())->findOrFail($id);
    }
}
