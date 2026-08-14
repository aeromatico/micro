<?php namespace Aero\MasterAds;

use System\Classes\PluginBase;

/**
 * Master Ads — Plugin bootstrap
 *
 * AI-driven Meta Ads optimizer for OctoberCMS 4. Multi-tenant SaaS that
 * synchronizes Meta Ads campaigns, runs LLM-based analyses, and applies
 * recommendations back to Meta with full audit trail.
 *
 * Wired here:
 *   - `register()`         — three artisan commands (sync-all, analyze, rotate-tokens).
 *   - `registerSchedule()` — recurring runs for the above (every 4 h / daily 03:00 / daily 06:00).
 *   - `registerPermissions()` / `registerNavigation()` — backend ACL + menu.
 *
 * `boot()` remains an empty stub and will be populated by later tasks
 * (observers, listeners, route extensions).
 *
 * Validates: Requirements 10.4, 10.5, 10.6, 19.2, 19.3, 20.3, 20.4 (master-ads spec)
 */
class Plugin extends PluginBase
{
    /**
     * Plugin metadata exposed to OctoberCMS.
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Master Ads',
            'description' => 'Optimizador de campañas Meta Ads asistido por IA — SaaS multi-tenant para OctoberCMS 4.',
            'author'      => 'aero',
            'icon'        => 'icon-bullhorn',
        ];
    }

    /**
     * Register the plugin's artisan commands.
     *
     * Exposes three CLI entry points used by the scheduler and operators:
     *
     *   - `masterads:sync-all`       — fan-out sync for every connected
     *                                  MetaAccount (scheduled every 4 h).
     *   - `masterads:analyze`        — single-target analysis or `--auto`
     *                                  daily sweep for auto-analyze workspaces.
     *   - `masterads:rotate-tokens`  — refresh Meta access tokens that are
     *                                  within N days of expiry (daily 03:00).
     *
     * Validates: Requirements 10.4, 10.5, 10.6, 19.2, 19.3 (master-ads spec)
     */
    public function register(): void
    {
        $this->registerConsoleCommand('masterads:sync-all', \Aero\MasterAds\Console\SyncAllCommand::class);
        $this->registerConsoleCommand('masterads:analyze', \Aero\MasterAds\Console\AnalyzeCommand::class);
        $this->registerConsoleCommand('masterads:rotate-tokens', \Aero\MasterAds\Console\RotateTokensCommand::class);
    }

    /**
     * Boot the plugin: wire observers and event listeners.
     *
     * - Recommendation::observe(RecommendationObserver) — auto-apply flow (Req 9.8, 9.9).
     * - Subscription::observe(SubscriptionObserver) — period rollover logging (Req 9.6).
     * - Event listeners for `aero.masterads.recommendation_generated` (both legacy
     *   string event and modern RecommendationGenerated class event) — Req 13.5.
     *
     * Validates: Requirements 9.6, 9.8, 13.5
     */
    public function boot(): void
    {
        // Register Eloquent observers.
        \Aero\MasterAds\Models\Recommendation::observe(\Aero\MasterAds\Observers\RecommendationObserver::class);
        \Aero\MasterAds\Models\Subscription::observe(\Aero\MasterAds\Observers\SubscriptionObserver::class);

        // Register event listeners — both legacy string events and modern class events.
        \Event::listen('aero.masterads.recommendation_generated', function ($aiAnalysis) {
            app(\Aero\MasterAds\Listeners\NotifyRecommendationListener::class)->handle($aiAnalysis);
        });
        \Event::listen(\Aero\MasterAds\Events\RecommendationGenerated::class, function ($event) {
            app(\Aero\MasterAds\Listeners\NotifyRecommendationListener::class)->handle($event->aiAnalysis);
        });
    }

    /**
     * Register scheduled tasks for the plugin's recurring jobs.
     *
     * Three schedules are wired up here, all guarded with `withoutOverlapping()`
     * so a long-running invocation never doubles up on the next tick:
     *
     *   - `masterads:sync-all` every 4 hours, additionally pinned to a single
     *     server (`onOneServer()`) so multi-node deployments don't fan out the
     *     same sync N times.
     *   - `masterads:rotate-tokens --days=7` daily at 03:00 to refresh Meta
     *     long-lived tokens before they expire.
     *   - `masterads:analyze --auto` daily at 06:00 to run analyses for
     *     workspaces flagged with `auto_analyze=true`.
     *
     * Each schedule is given a stable `name()` so the Laravel scheduler logs,
     * cache locks and `schedule:list` output identify it predictably.
     *
     * Validates: Requirements 10.4, 10.5, 10.6, 19.2, 19.3 (master-ads spec)
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     */
    public function registerSchedule($schedule): void
    {
        // Sync all Meta accounts every 4 hours, single-server, no overlap.
        $schedule->command('masterads:sync-all')
            ->everyFourHours()
            ->withoutOverlapping()
            ->onOneServer()
            ->name('aero-masterads-sync-all');

        // Rotate Meta tokens daily at 03:00.
        $schedule->command('masterads:rotate-tokens --days=7')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->name('aero-masterads-rotate-tokens');

        // Auto-analyze daily at 06:00 for workspaces with auto_analyze=true.
        $schedule->command('masterads:analyze --auto')
            ->dailyAt('06:00')
            ->withoutOverlapping()
            ->name('aero-masterads-analyze-auto');
    }

    /**
     * Register backend permissions exposed by the plugin.
     *
     * Nine fine-grained permissions, all grouped under the
     * `aero.masterads::lang.tab.master_ads` tab so admins see them together
     * in Settings → Administrators → Permissions.
     *
     * Validates: Requirements 12.1, 12.2, 17.7 (master-ads spec)
     */
    public function registerPermissions(): array
    {
        $tab = 'aero.masterads::lang.tab.master_ads';

        return [
            'aero.masterads.access_plugin' => [
                'tab'   => $tab,
                'label' => 'aero.masterads::lang.permissions.access_plugin',
            ],
            'aero.masterads.manage_workspaces' => [
                'tab'   => $tab,
                'label' => 'aero.masterads::lang.permissions.manage_workspaces',
            ],
            'aero.masterads.manage_meta_accounts' => [
                'tab'   => $tab,
                'label' => 'aero.masterads::lang.permissions.manage_meta_accounts',
            ],
            'aero.masterads.access_campaigns' => [
                'tab'   => $tab,
                'label' => 'aero.masterads::lang.permissions.access_campaigns',
            ],
            'aero.masterads.run_analysis' => [
                'tab'   => $tab,
                'label' => 'aero.masterads::lang.permissions.run_analysis',
            ],
            'aero.masterads.review_recommendations' => [
                'tab'   => $tab,
                'label' => 'aero.masterads::lang.permissions.review_recommendations',
            ],
            'aero.masterads.apply_recommendations' => [
                'tab'   => $tab,
                'label' => 'aero.masterads::lang.permissions.apply_recommendations',
            ],
            'aero.masterads.manage_ai_providers' => [
                'tab'   => $tab,
                'label' => 'aero.masterads::lang.permissions.manage_ai_providers',
            ],
            'aero.masterads.manage_billing' => [
                'tab'   => $tab,
                'label' => 'aero.masterads::lang.permissions.manage_billing',
            ],
        ];
    }

    /**
     * Register the backend navigation entry for Master Ads.
     *
     * Declares a single root menu item `masterads` (icon-bullhorn, order 500)
     * gated by `aero.masterads.access_plugin`, plus a side menu with one entry
     * per backend area (workspaces, meta accounts, campaigns, ad sets, ads,
     * recommendations, AI analyses, AI providers, plans, subscriptions). Each
     * side-menu entry is gated by the most specific permission for that area
     * so admins only see the sections they may operate on.
     *
     * The `url` values target controllers under `aero/masterads/{controller}`
     * which will be created in task 13.x; until then the links resolve to
     * 404s, which is intentional during scaffolding.
     *
     * Validates: Requirements 12.2, 18.2 (master-ads spec)
     */
    public function registerNavigation(): array
    {
        return [
            'masterads' => [
                'label'       => 'aero.masterads::lang.tab.master_ads',
                'url'         => \Backend::url('aero/masterads/workspaces'),
                'icon'        => 'icon-bullhorn',
                'permissions' => ['aero.masterads.access_plugin'],
                'order'       => 500,
                'sideMenu' => [
                    'workspaces' => [
                        'label'       => 'aero.masterads::lang.nav.workspaces',
                        'icon'        => 'icon-building',
                        'url'         => \Backend::url('aero/masterads/workspaces'),
                        'permissions' => ['aero.masterads.manage_workspaces'],
                    ],
                    'metaaccounts' => [
                        'label'       => 'aero.masterads::lang.nav.meta_accounts',
                        'icon'        => 'icon-facebook',
                        'url'         => \Backend::url('aero/masterads/metaaccounts'),
                        'permissions' => ['aero.masterads.manage_meta_accounts'],
                    ],
                    'campaigns' => [
                        'label'       => 'aero.masterads::lang.nav.campaigns',
                        'icon'        => 'icon-bullseye',
                        'url'         => \Backend::url('aero/masterads/campaigns'),
                        'permissions' => ['aero.masterads.access_campaigns'],
                    ],
                    'adsets' => [
                        'label'       => 'aero.masterads::lang.nav.ad_sets',
                        'icon'        => 'icon-users',
                        'url'         => \Backend::url('aero/masterads/adsets'),
                        'permissions' => ['aero.masterads.access_campaigns'],
                    ],
                    'ads' => [
                        'label'       => 'aero.masterads::lang.nav.ads',
                        'icon'        => 'icon-image',
                        'url'         => \Backend::url('aero/masterads/ads'),
                        'permissions' => ['aero.masterads.access_campaigns'],
                    ],
                    'recommendations' => [
                        'label'       => 'aero.masterads::lang.nav.recommendations',
                        'icon'        => 'icon-lightbulb-o',
                        'url'         => \Backend::url('aero/masterads/recommendations'),
                        'permissions' => ['aero.masterads.review_recommendations'],
                    ],
                    'aianalyses' => [
                        'label'       => 'aero.masterads::lang.nav.ai_analyses',
                        'icon'        => 'icon-magic',
                        'url'         => \Backend::url('aero/masterads/aianalyses'),
                        'permissions' => ['aero.masterads.run_analysis'],
                    ],
                    'aiproviders' => [
                        'label'       => 'aero.masterads::lang.nav.ai_providers',
                        'icon'        => 'icon-plug',
                        'url'         => \Backend::url('aero/masterads/aiproviders'),
                        'permissions' => ['aero.masterads.manage_ai_providers'],
                    ],
                    'plans' => [
                        'label'       => 'aero.masterads::lang.nav.plans',
                        'icon'        => 'icon-cubes',
                        'url'         => \Backend::url('aero/masterads/plans'),
                        'permissions' => ['aero.masterads.manage_billing'],
                    ],
                    'subscriptions' => [
                        'label'       => 'aero.masterads::lang.nav.subscriptions',
                        'icon'        => 'icon-credit-card',
                        'url'         => \Backend::url('aero/masterads/subscriptions'),
                        'permissions' => ['aero.masterads.manage_billing'],
                    ],
                ],
            ],
        ];
    }
}
