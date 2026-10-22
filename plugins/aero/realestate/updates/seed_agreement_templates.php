<?php

use October\Rain\Database\Updates\Migration;

/**
 * Modelos base de la plataforma (tenant_id null): arras, alquiler y anticrético. Son un punto de partida
 * ORIENTATIVO sin revisión legal; cada inmobiliaria los duplica, los adapta y los hace revisar por su abogado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_realestate_templates')) {
            return;
        }

        foreach (\Aero\Realestate\Classes\DefaultTemplates::all() as $type => $tpl) {
            \Aero\Realestate\Models\AgreementTemplate::firstOrCreate(
                ['tenant_id' => null, 'type' => $type],
                ['name' => $tpl['name'], 'body' => $tpl['body'], 'legal_status' => 'draft', 'is_active' => true]
            );
        }
    }

    public function down(): void
    {
        // Datos semilla globales: no se borran.
    }
};
