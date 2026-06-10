<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // ── OctoberCMS backend roles ──────────────────────────────────────────
        $this->seedBackendRoles();

        // ── RainLab.User frontend groups ──────────────────────────────────────
        $this->seedUserGroups();
    }

    protected function seedBackendRoles(): void
    {
        $roles = [
            [
                'name'        => 'Superadmin',
                'code'        => 'superadmin',
                'description' => 'Control total de la plataforma SaaS.',
                'permissions' => ['aero.sites.*' => 1],
            ],
            [
                'name'        => 'Tenant Admin',
                'code'        => 'tenant_admin',
                'description' => 'Administrador de un tenant — gestiona páginas, SEO, contacto y canales.',
                'permissions' => [
                    'aero.sites.manage_pages'      => 1,
                    'aero.sites.manage_seo'        => 1,
                    'aero.sites.manage_contact'    => 1,
                    'aero.sites.view_submissions'  => 1,
                    'aero.sites.manage_api_tokens' => 1,
                ],
            ],
        ];

        foreach ($roles as $data) {
            \Backend\Models\UserRole::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }

    protected function seedUserGroups(): void
    {
        if (!class_exists(\RainLab\User\Models\UserGroup::class)) {
            return;
        }

        $groups = [
            [
                'name'        => 'Superadmin',
                'code'        => 'superadmin',
                'description' => 'Propietario de la plataforma. Acceso total.',
            ],
            [
                'name'        => 'Admin',
                'code'        => 'admin',
                'description' => 'Administrador de tenant. Gestiona su microsite.',
            ],
            [
                'name'        => 'Moderador',
                'code'        => 'moderator',
                'description' => 'Moderador de contenido dentro de un tenant.',
            ],
        ];

        foreach ($groups as $data) {
            \RainLab\User\Models\UserGroup::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }

    public function down(): void
    {
        \Backend\Models\UserRole::whereIn('code', ['superadmin', 'tenant_admin'])->delete();

        if (class_exists(\RainLab\User\Models\UserGroup::class)) {
            \RainLab\User\Models\UserGroup::whereIn('code', ['superadmin', 'admin', 'moderator'])->delete();
        }
    }
};
