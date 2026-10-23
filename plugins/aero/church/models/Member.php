<?php namespace Aero\Church\Models;

use Carbon\Carbon;
use Model;

/**
 * Miembro, visitante o asistente. Privado por tenant. Un menor debe tener un tutor
 * (otro miembro); los datos de menores no se comunican sin el consentimiento del tutor.
 */
class Member extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Church\Classes\TenantOwned;

    public const STATUSES = [
        'visitor'  => 'Visitante',
        'new'      => 'Nuevo asistente',
        'active'   => 'Miembro activo',
        'inactive' => 'Miembro inactivo',
    ];

    public const FAMILY_ROLES = [
        'head'   => 'Cabeza de familia',
        'spouse' => 'Cónyuge',
        'child'  => 'Hijo/a',
        'other'  => 'Otro',
    ];

    public $table = 'aero_church_members';

    public static $audited = true;
    public static $auditLabel = 'miembro';
    public static $auditType = 'member';

    public $fillable = [
        'tenant_id', 'branch_id', 'family_id', 'guardian_id', 'first_name', 'last_name', 'document', 'gender',
        'civil_status', 'birth_date', 'marriage_date', 'phone', 'email', 'address', 'status', 'family_role',
        'joined_at', 'baptized', 'baptism_date', 'consent_messages', 'consent_data', 'notes',
    ];

    public $rules = [
        'first_name' => 'required|string|max:255',
        'email'      => 'nullable|email|max:255',
        'status'     => 'required|in:visitor,new,active,inactive',
    ];

    public $dates = ['birth_date', 'marriage_date', 'joined_at', 'baptism_date', 'consent_at'];

    public $belongsTo = [
        'branch'   => [Branch::class, 'key' => 'branch_id'],
        'family'   => [Family::class, 'key' => 'family_id'],
        'guardian' => [self::class, 'key' => 'guardian_id'],
    ];

    public $hasMany = [
        'groupMemberships' => [GroupMember::class, 'key' => 'member_id'],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible([
            'branch_id' => Branch::class, 'family_id' => Family::class, 'guardian_id' => self::class,
        ]);
    }

    public function beforeSave(): void
    {
        // Registra cuándo se dio el consentimiento (y lo limpia si se retira).
        if ($this->consent_messages || $this->consent_data) {
            $this->consent_at = $this->consent_at ?: now();
        } else {
            $this->consent_at = null;
        }
    }

    public function beforeCreate(): void
    {
        $this->joined_at = $this->joined_at ?: ($this->status === 'active' ? now()->toDateString() : null);
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value === null || $value === '' ? null : preg_replace('/[^\d+]/', '', (string) $value);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? (int) Carbon::parse($this->birth_date)->diffInYears(now(), true) : null;
    }

    public function getIsMinorAttribute(): bool
    {
        return $this->age !== null && $this->age < 18;
    }

    public function getStatusOptions(): array
    {
        return self::STATUSES;
    }

    public function getFamilyRoleOptions(): array
    {
        return self::FAMILY_ROLES;
    }

    public function getGenderOptions(): array
    {
        return ['m' => 'Masculino', 'f' => 'Femenino'];
    }

    public function getCivilStatusOptions(): array
    {
        return ['single' => 'Soltero/a', 'married' => 'Casado/a', 'cohabiting' => 'Unión libre', 'divorced' => 'Divorciado/a', 'widowed' => 'Viudo/a'];
    }

    public function getBranchIdOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getFamilyIdOptions(): array
    {
        return Family::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getGuardianIdOptions(): array
    {
        return self::visible()->orderBy('first_name')->get()->pluck('full_name', 'id')->all();
    }

    /** Miembros con cumpleaños en el mes dado (por defecto, el actual). */
    public function scopeBirthdayInMonth($query, ?int $month = null)
    {
        return $query->whereMonth('birth_date', $month ?: now()->month);
    }
}
