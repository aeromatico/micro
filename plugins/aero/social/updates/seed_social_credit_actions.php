<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;

/**
 * Una acción facturable por red: el superadmin ajusta el costo de cada una en
 * Créditos → Acciones. Idempotente; no pisa costos ya ajustados.
 */
return new class extends Seeder
{
    public function run(): void
    {
        if (!class_exists(\Aero\Credits\Models\CreditAction::class)
            || !Schema::hasTable('aero_credits_types')
            || !Schema::hasTable('aero_credits_actions')) {
            return;
        }

        // Bronce = moneda de las acciones por uso (mensajes, IA, SMS).
        $type = \Aero\Credits\Models\CreditType::where('code', 'bronce')->first()
            ?: \Aero\Credits\Models\CreditType::orderBy('sort_order')->first();

        if (!$type) {
            return;
        }

        foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram'] as $code => $label) {
            \Aero\Credits\Models\CreditAction::firstOrCreate(
                ['code' => "social.post.{$code}"],
                [
                    'label'              => "Publicación en {$label}",
                    'plugin'             => 'Aero.Social',
                    'credit_type_id'     => $type->id,
                    'default_cost'       => 1,
                    'category'           => 'Redes sociales',
                    'public_label'       => "Publicación en {$label}",
                    'public_description' => "Cada post publicado en una cuenta de {$label}. Si la publicación falla, se devuelve.",
                    'unit'               => 'publicación',
                    'is_public'          => true,
                ]
            );
        }
    }
};
