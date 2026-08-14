<?php namespace Aero\Sites\Console;

use Aero\Sites\Models\DesignTheme;
use Aero\Sites\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Asigna un DesignTheme a tenants que todavía no tienen uno (creados antes
 * del sistema de temas, o provisionados sin generación IA de por medio).
 *
 * Elección determinística: primero filtra por afinidad de nicho, luego
 * dentro de esos candidatos elige el de paleta más cercana al primary_color
 * legacy del tenant (si lo tiene) — si no, uno al azar entre los afines.
 */
class AssignDesignThemes extends Command
{
    protected $signature = 'aero.sites:assign-themes {--tenant= : Limitar a un handle de tenant específico} {--dry-run : Mostrar la asignación sin guardarla}';

    protected $description = 'Asigna un DesignTheme a los tenants que todavía no tienen uno.';

    public function handle(): int
    {
        $query = Tenant::whereNull('design_theme_id');

        if ($handle = $this->option('tenant')) {
            $query->where('handle', $handle);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->info('No hay tenants sin design_theme_id.');
            return 0;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($tenants as $tenant) {
            $theme = $this->pickTheme($tenant);

            if (!$theme) {
                $this->warn("- {$tenant->handle}: no hay ningún DesignTheme activo disponible.");
                continue;
            }

            $suffix = $dryRun ? ' [dry-run, no guardado]' : '';
            $this->line("- {$tenant->handle} (nicho: {$tenant->niche_type}) -> {$theme->handle}{$suffix}");

            if (!$dryRun) {
                $tenant->design_theme_id = $theme->id;
                $tenant->save();
            }
        }

        return 0;
    }

    protected function pickTheme(Tenant $tenant): ?DesignTheme
    {
        $affine = DesignTheme::active()->forNiche($tenant->niche_type)->get();

        if ($affine->isEmpty()) {
            return DesignTheme::active()->inRandomOrder()->first();
        }

        if (!$tenant->primary_color) {
            return $affine->random();
        }

        return $affine->sortBy(
            fn (DesignTheme $theme) => $this->colorDistance($tenant->primary_color, $theme->colors['primary'] ?? '#000000')
        )->first();
    }

    protected function colorDistance(string $hexA, string $hexB): float
    {
        [$rA, $gA, $bA] = $this->hexToRgb($hexA);
        [$rB, $gB, $bB] = $this->hexToRgb($hexB);

        return sqrt(($rA - $rB) ** 2 + ($gA - $gB) ** 2 + ($bA - $bB) ** 2);
    }

    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return [0, 0, 0];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
