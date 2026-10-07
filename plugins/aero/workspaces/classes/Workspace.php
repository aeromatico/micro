<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Hire;
use Aero\Workspaces\Models\Settings;
use Aero\Workspaces\Models\Skill;
use Aero\Workspaces\Models\Staff;
use Aero\Workspaces\Models\Task;

/**
 * Lo que ve un tenant del catálogo: mercado, equipo, skills y puntos. Lo usan
 * las pantallas del panel y las herramientas del MCP, así que ambos muestran lo
 * mismo. NUNCA expone el prompt de sistema ni el modelo del agente.
 */
class Workspace
{
    public const AVATARS = 12;
    public const MAX_PERSONAL_SKILLS = 50;
    public const SKILL_COLORS = ['#2f7fd0', '#8a54c7', '#d6407a', '#d9501a', '#1f9d63', '#c58a0b'];

    /** Índice del recorte en la hoja de avatares/sprites (`crop:N`; sin él, uno estable por id). */
    public static function avatarIndex(Staff $staff): int
    {
        if (preg_match('/^crop:(\d+)$/', (string) $staff->avatar, $m)) {
            return min(static::AVATARS - 1, (int) $m[1]);
        }

        return ((int) $staff->id - 1) % static::AVATARS;
    }

    /** Ids contratados por el tenant. */
    public static function hiredIds(int $tenantId): array
    {
        return Hire::forTenant($tenantId)->pluck('staff_id')->map(fn ($v) => (int) $v)->all();
    }

    /** Tarifa por encargo (puntos) de un agente. */
    public static function taskFee(Staff $staff): int
    {
        return (int) round((float) ($staff->taskRateRows->firstWhere('task_type', 'encargo')->fee ?? 0));
    }

    /**
     * Forma pública de un agente. `$hired`: ya está en el equipo del tenant.
     */
    public static function agent(Staff $staff, bool $hired = false, ?int $contracts = null): array
    {
        return [
            'id'           => (int) $staff->id,
            'slug'         => $staff->slug,
            'name'         => $staff->name,
            'role'         => $staff->role,
            'category'     => $staff->category,
            'rarity'       => $staff->rarity,
            'bio'          => (string) $staff->bio,
            'tags'         => array_values((array) $staff->tags),
            'capabilities' => (array) $staff->capabilities,
            'guide'        => array_values((array) $staff->guide),
            'avatar'       => static::avatarIndex($staff),
            'orchestrator' => (bool) $staff->is_orchestrator,
            // Trabaja de verdad (tiene herramientas por sus skills) en vez de simular.
            'live'         => AgentRunner::isLive($staff),
            'hired'        => $hired || (bool) $staff->is_orchestrator,
            'hire_fee'     => (int) round((float) $staff->hire_fee),
            'task_fee'     => static::taskFee($staff),
            'contracts'    => $contracts ?? 0,
            'skills'       => $staff->skills->map(fn (Skill $s) => static::skill($s))->values()->all(),
        ];
    }

    public static function skill(Skill $skill, ?int $users = null): array
    {
        return [
            'id'          => (int) $skill->id,
            'slug'        => $skill->slug,
            'name'        => $skill->name,
            'kind'        => $skill->kind,
            'description' => (string) $skill->description,
            'color'       => static::SKILL_COLORS[crc32((string) $skill->slug) % count(static::SKILL_COLORS)],
            'users'       => $users,
        ];
    }

    /** Todo el catálogo activo (menos el orquestador, que no se vende) con su estado para el tenant. */
    public static function market(int $tenantId): array
    {
        $hired = static::hiredIds($tenantId);
        $counts = Hire::selectRaw('staff_id, count(*) as n')->groupBy('staff_id')->pluck('n', 'staff_id');

        return Staff::active()->where('is_orchestrator', false)->with(['skills', 'rate', 'taskRateRows'])
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Staff $s) => static::agent($s, in_array((int) $s->id, $hired, true), (int) ($counts[$s->id] ?? 0)))
            ->all();
    }

    /** Orquestador (siempre) + los contratados activos. */
    public static function team(int $tenantId): array
    {
        $orchestrator = Staff::orchestrator();
        $hired = static::hiredIds($tenantId);
        $members = Staff::active()->whereIn('id', $hired ?: [0])->where('is_orchestrator', false)
            ->with(['skills', 'rate', 'taskRateRows'])->orderBy('sort_order')->get();

        $team = [];

        if ($orchestrator) {
            $orchestrator->load(['skills', 'rate', 'taskRateRows']);
            $team[] = static::agent($orchestrator, true);
        }

        foreach ($members as $member) {
            $team[] = static::agent($member, true);
        }

        return $team;
    }

    public static function skills(int $tenantId): array
    {
        $users = \DB::table('aero_workspaces_staff_skill as ss')
            ->join('aero_workspaces_staff as s', 's.id', '=', 'ss.staff_id')
            ->where('s.is_active', true)->selectRaw('ss.skill_id, count(*) as n')->groupBy('ss.skill_id')->pluck('n', 'skill_id');

        $order = ['official' => 0, 'hub' => 1, 'personal' => 2];

        return Skill::where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))
            ->orderBy('name')->get()
            ->sortBy(fn (Skill $s) => [$order[$s->kind] ?? 9, mb_strtolower($s->name)])->values()
            ->map(fn (Skill $s) => static::skill($s, (int) ($users[$s->id] ?? 0)))->all();
    }

    /** Skill personal del tenant. Solo nombre y «cuándo usarla». */
    public static function createSkill(int $tenantId, string $name, string $description): array
    {
        $name = trim($name);
        $description = trim($description);

        if ($name === '' || mb_strlen($name) > 40 || $description === '' || mb_strlen($description) > 200) {
            throw new \DomainException('Escribe un nombre (hasta 40 caracteres) y cuándo usarla (hasta 200).');
        }

        if (Skill::where('tenant_id', $tenantId)->count() >= static::MAX_PERSONAL_SKILLS) {
            throw new \DomainException('Llegaste al máximo de ' . static::MAX_PERSONAL_SKILLS . ' skills personales.');
        }

        $base = \Illuminate\Support\Str::slug($name) ?: 'skill';
        $slug = $base;
        $n = 2;

        while (Skill::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }

        $skill = Skill::create(['tenant_id' => $tenantId, 'kind' => 'personal', 'name' => $name, 'slug' => $slug, 'description' => $description]);

        return static::skill($skill, 0);
    }

    /** Saldo de puntos del tenant, o null si el cobro está apagado o Credits no está. */
    public static function points(int $tenantId): ?int
    {
        if (!Settings::chargeEnabled() || !class_exists(\Aero\Credits\Classes\Credits::class)) {
            return null;
        }

        try {
            return \Aero\Credits\Classes\Credits::balance($tenantId, Settings::creditTypeCode());
        }
        catch (\Throwable) {
            return null;
        }
    }

    /** Resumen para la cabecera de las pantallas y para el MCP. */
    public static function summary(int $tenantId): array
    {
        Task::settleDue($tenantId);

        return [
            'points'         => static::points($tenantId),
            'charging'       => Settings::chargeEnabled(),
            'team_size'      => count(static::hiredIds($tenantId)) + (Staff::orchestrator() ? 1 : 0),
            'running_task'   => ($t = Task::forTenant($tenantId)->where('status', 'running')->orderByDesc('id')->first()) ? Tasks::payload($t) : null,
        ];
    }
}
