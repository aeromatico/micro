<?php namespace Aero\Sites\Classes\Niches;

interface NicheManagerInterface
{
    public function getHandle(): string;
    public function getName(): string;
    public function getIcon(): string;
    public function getFeatures(): array;
    public function getDefaultPages(): array;
    public function getSeoDefaults(): array;
    public function getContactDefaults(): array;
    public function getRecommendedNotification(): string;

    /**
     * Prompt de ejemplo (con placeholders) para autocompletar la descripción
     * del negocio en el panel de generación con IA, cuando el arquetipo
     * elegido no trae uno propio.
     */
    public function getBasePrompt(): string;

    /** Tono/estilo de escritura recomendado para este nicho, usado como
     * respaldo cuando el arquetipo elegido no define el suyo. */
    public function getToneInstructions(): string;

    /** Público objetivo tipo de este nicho, ídem getToneInstructions(). */
    public function getTargetAudience(): string;

    public function provision(\Aero\Sites\Models\Tenant $tenant): void;
}
