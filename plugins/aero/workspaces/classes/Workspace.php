<?php namespace Aero\Workspaces\Classes;

use Aero\Workspaces\Models\Category;
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
        return Billing::rate($staff, Billing::TASK);
    }

    /** Código del plan del tenant (null si no hay Sites o no tiene plan). */
    public static function planCode(int $tenantId): ?string
    {
        if ($tenantId <= 0 || !class_exists(\Aero\Sites\Models\Tenant::class)) {
            return null;
        }

        try {
            $planId = \Aero\Sites\Models\Tenant::where('id', $tenantId)->value('plan_id');

            return $planId ? (\Aero\Sites\Models\Plan::where('id', $planId)->value('code') ?: null) : null;
        }
        catch (\Throwable) {
            return null;
        }
    }

    /** [código => nombre] de los planes de la plataforma (para mostrar a qué plan corresponde un agente). */
    public static function planNames(): array
    {
        static $names = null;

        if ($names === null) {
            try {
                $names = class_exists(\Aero\Sites\Models\Plan::class) ? \Aero\Sites\Models\Plan::where('is_active', true)->orderBy('sort_order')->pluck('name', 'code')->all() : [];
            }
            catch (\Throwable) {
                $names = [];
            }
        }

        return $names;
    }

    /** Categorías dinámicas: [código => nombre] (las activas, en orden). */
    public static function categories(): array
    {
        return Category::options();
    }

    /** [código => ['label', 'color']] de los tipos de crédito. */
    public static function creditTypes(): array
    {
        static $types = null;

        if ($types === null) {
            $types = [];

            try {
                if (class_exists(\Aero\Credits\Models\CreditType::class)) {
                    foreach (\Aero\Credits\Models\CreditType::active()->get() as $t) {
                        $types[$t->code] = ['label' => (string) $t->label, 'color' => (string) $t->color];
                    }
                }
            }
            catch (\Throwable) {
                // sin Credits: la tarifa se muestra sin nombre de tipo
            }
        }

        return $types;
    }

    /**
     * Forma pública de un agente. `$hired`: ya está en el equipo del tenant.
     * `$tenantPlan`: plan del tenant que mira (null = vista pública, sin tenant).
     */
    public static function agent(Staff $staff, bool $hired = false, ?int $contracts = null, ?string $tenantPlan = null, bool $forTenant = false): array
    {
        $plans = array_values(array_filter((array) $staff->plan_codes));
        $names = static::planNames();
        $type = Billing::creditType($staff);
        $paid = $staff->fee_mode === 'credits';
        $category = static::categories()[$staff->category] ?? ucfirst((string) $staff->category);

        return [
            'id'           => (int) $staff->id,
            'slug'         => $staff->slug,
            'name'         => $staff->name,
            'role'         => $staff->role,
            'category'     => $staff->category,
            'category_label' => $category,
            'quality'      => max(1, min(5, (int) $staff->quality)),
            'status'       => $staff->is_orchestrator ? Staff::ONLINE : (string) $staff->status,
            'status_label' => Staff::STATUSES[$staff->is_orchestrator ? Staff::ONLINE : $staff->status] ?? (string) $staff->status,
            'bio'          => (string) $staff->bio,
            'tags'         => array_values((array) $staff->tags),
            'capabilities' => (array) $staff->capabilities,
            'guide'        => array_values((array) $staff->guide),
            'avatar'       => static::avatarIndex($staff),
            'orchestrator' => (bool) $staff->is_orchestrator,
            // Trabaja de verdad (tiene herramientas por sus skills) en vez de simular.
            'live'         => AgentRunner::isLive($staff),
            'hired'        => $hired || (bool) $staff->is_orchestrator,
            // A qué planes corresponde (vacío = todos) y si el plan del tenant que mira lo incluye.
            'plans'        => array_map(fn ($c) => ['code' => $c, 'name' => $names[$c] ?? strtoupper($c)], $plans),
            'plan_ok'      => !$forTenant || !$plans || ($tenantPlan !== null && in_array($tenantPlan, $plans, true)),
            'fee'          => [
                'mode'         => $paid ? 'credits' : 'free',
                'credit_type'  => $paid ? $type : null,
                'credit_label' => $paid ? (static::creditTypes()[$type]['label'] ?? $type) : null,
                'credit_color' => $paid ? (static::creditTypes()[$type]['color'] ?? null) : null,
            ],
            'hire_fee'     => Billing::hireFee($staff),
            'task_fee'     => static::taskFee($staff),
            'chat_fee'     => Billing::rate($staff, Billing::TURN),
            // Contratos reales de los tenants + los de referencia que fija el superadmin (3 a 30 al sembrar).
            'contracts'    => ($contracts ?? 0) + max(0, (int) ($staff->base_contracts ?? 0)),
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

    /**
     * Todo el catálogo listado (online y en entrenamiento; sin el orquestador, que no
     * se vende) con su estado para el tenant. `$tenantId` 0 = vista pública.
     */
    public static function market(int $tenantId): array
    {
        $hired = static::hiredIds($tenantId);
        $counts = Hire::selectRaw('staff_id, count(*) as n')->groupBy('staff_id')->pluck('n', 'staff_id');
        $plan = static::planCode($tenantId);

        return Staff::listed()->where('is_orchestrator', false)->with(['skills', 'rate', 'taskRateRows'])
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (Staff $s) => static::agent($s, in_array((int) $s->id, $hired, true), (int) ($counts[$s->id] ?? 0), $plan, $tenantId > 0))
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
            $team[] = static::agent($orchestrator, true, null, null, false);
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
