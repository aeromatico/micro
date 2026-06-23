# Implementation Plan: Master Ads

## Overview

Plan de implementación bottom-up para el plugin `Aero\MasterAds` (OctoberCMS 4 / PHP 8.2+). Cada tarea es un paso atómico de codificación que construye sobre los anteriores, sin código huérfano. El orden sigue el flujo: scaffold → datos → permisos → integraciones externas (Meta + IA) → motor de optimización → billing → jobs → controllers backend → comandos artisan → eventos → tests property-based → documentación.

Las sub-tareas marcadas con `*` (tests unitarios, de integración y property-based) son opcionales y pueden saltarse en un MVP express, pero **deben implementarse para certificar las 9 propiedades de corrección** definidas en `design.md`.

Convenciones aplicables a TODAS las tareas:
- Plugin path: `plugins/aero/masterads/`
- Namespace: `Aero\MasterAds`
- Lenguaje: PHP 8.2+
- Compatibilidad obligatoria: RainLab Builder (estructura `models/{Modelo}/` y `controllers/{nombre}/` con yamls)
- Strings visibles vía `aero.masterads::lang.<key>` en `lang/es/lang.php`

---

## Tasks

- [x] 1. Scaffold del plugin y estructura base
  - [x] 1.1 Crear `plugins/aero/masterads/Plugin.php` y `composer.json`
    - Implementar `Plugin.php` con `pluginDetails()`, `boot()`, `register()`, `registerSchedule()` (vacío por ahora)
    - Declarar `composer.json` con dependencias: `october/rain ^4.2`, `guzzlehttp/guzzle ^7.8`, `illuminate/encryption ^12.0`
    - Namespace PSR-4: `Aero\\MasterAds\\` => `""`
    - _Requirements: 20.3, 20.4, 20.5_
  - [x] 1.2 Crear `plugins/aero/masterads/routes.php` con stub del callback OAuth
    - Declarar `Route::get('aero/masterads/oauth/meta/callback', ...)` apuntando a placeholder (handler real en tarea 5.2)
    - _Requirements: 11.1, 11.2_
  - [x] 1.3 Crear `plugins/aero/masterads/lang/es/lang.php` con esqueleto de cadenas
    - Definir keys base: `plugin.name`, `plugin.description`, `tab.master_ads`, etiquetas comunes (`name`, `status`, `created_at`, etc.)
    - _Requirements: 18.1, 18.2, 18.3_
  - [x] 1.4 Crear `.env.example` y entradas en `config/services.php` para Meta + OpenRouter
    - Variables: `META_APP_ID`, `META_APP_SECRET`, `META_OAUTH_REDIRECT`, `META_GRAPH_API_VERSION`, `OPENROUTER_API_KEY`, `OPENROUTER_BASE_URL`, `OPENROUTER_DEFAULT_MODEL`
    - _Requirements: 15.4_
  - [x] 1.5 Crear `updates/version.yaml` con la versión inicial 1.0.1 sin migraciones
    - Comentario inicial; las migraciones se registrarán incrementalmente en la tarea 2
    - _Requirements: 17.3, 17.5_

- [x] 2. Migraciones de base de datos (14 tablas)
  - [x] 2.1 Migración `create_workspaces_table.php` y `create_workspace_user_table.php`
    - `aero_masterads_workspaces`: id, name (string 120), slug (string unique), owner_id (FK backend_users), settings JSON, timestamps
    - `aero_masterads_workspace_user`: workspace_id, user_id, role enum(owner|admin|viewer), unique(workspace_id, user_id)
    - Registrar ambas en `version.yaml` con orden 1.0.1 y 1.0.2
    - _Requirements: 1.1, 1.2, 1.5, 17.3, 17.4, 17.5_
  - [x] 2.2 Migraciones de billing: `plans`, `subscriptions`, `usage_records`
    - `aero_masterads_plans`: code (alpha_dash unique), monthly_price decimal, max_meta_accounts int, max_analyses_month int, auto_apply_allowed bool, timestamps
    - `aero_masterads_subscriptions`: workspace_id (FK), plan_id (FK), status enum, period_start date, period_end date, timestamps
    - `aero_masterads_usage_records`: subscription_id (FK), metric enum(analysis|sync|applied_action), qty int, recorded_at datetime, index(subscription_id, metric, recorded_at)
    - _Requirements: 9.1, 9.2, 9.7, 14.4, 17.4, 17.5_
  - [x] 2.3 Migración `create_ai_providers_table.php`
    - `aero_masterads_ai_providers`: workspace_id (FK nullable), name (string 120), driver enum(openrouter|openai|anthropic|custom), model string, api_key text encrypted, is_default bool, settings JSON, timestamps
    - _Requirements: 5.1, 5.2, 5.5, 17.4, 17.5_
  - [x] 2.4 Migración `create_meta_accounts_table.php`
    - `aero_masterads_meta_accounts`: workspace_id (FK), meta_act_id string match `act_\d+`, currency char(3), access_token text, refresh_token text nullable, expires_at datetime, last_synced_at datetime nullable, last_error text nullable, timestamps
    - _Requirements: 2.4, 17.4, 17.5_
  - [x] 2.5 Migraciones de jerarquía Meta: `campaigns`, `ad_sets`, `ads`
    - `aero_masterads_campaigns`: meta_account_id (FK), meta_id string unique, name (string 255), objective string, status enum, daily_budget decimal nullable, timestamps
    - `aero_masterads_ad_sets`: campaign_id (FK), meta_id string unique, name, status, targeting JSON, daily_budget decimal nullable, timestamps
    - `aero_masterads_ads`: ad_set_id (FK), meta_id string unique, name, status, creative JSON, timestamps
    - _Requirements: 3.2, 3.3, 17.4, 17.5_
  - [x] 2.6 Migración `create_insights_table.php` con índice único compuesto
    - `aero_masterads_insights`: entity_type enum(campaign|adset|ad), entity_id bigint, date date, impressions int, clicks int, spend decimal(12,4), conversions int
    - **UNIQUE(entity_type, entity_id, date)** + INDEX(entity_type, entity_id, date)
    - `timestamps = false`
    - _Requirements: 4.2, 4.5, 14.4, 17.4, 17.5_
  - [x] 2.7 Migraciones IA: `ai_analyses`, `recommendations`, `applied_actions`
    - `aero_masterads_ai_analyses`: workspace_id (FK), ai_provider_id (FK), target_type enum, target_id bigint, status enum, prompt_payload JSON, raw_response JSON, metrics_snapshot JSON, tokens_used int, cost_usd decimal(10,6), error_message text nullable, soft deletes, timestamps
    - `aero_masterads_recommendations`: ai_analysis_id (FK), action_type enum, severity enum, status enum, rationale text, payload JSON, expected_impact JSON, timestamps + INDEX(ai_analysis_id, status), INDEX(status, severity)
    - `aero_masterads_applied_actions`: recommendation_id (FK), applied_by (FK backend_users), success bool, before_state JSON, after_state JSON nullable, meta_response JSON, timestamps + UNIQUE parcial(recommendation_id, success)
    - _Requirements: 6.10, 7.12, 8.1, 8.2, 8.6, 14.4, 17.4, 17.5_

- [x] 3. Checkpoint - Verificar migraciones
  - Ejecutar `php artisan october:up` en entorno local; asegurar que las 14 tablas se crean sin errores. Asegurarse de que todos los tests escritos hasta ahora pasen; preguntar al usuario si surgen dudas.

- [x] 4. Modelos Eloquent + YAMLs Builder (carpeta por modelo)
  - [x] 4.1 Modelo `Workspace` en `models/Workspace/` con `fields.yaml` y `columns.yaml`
    - Trait `Validation`, reglas, `belongsTo owner`, `belongsToMany members` (pivot role), `hasMany meta_accounts/subscriptions`
    - Implementar guard contra `delete()` si tiene Subscriptions activas o MetaAccounts conectadas
    - _Requirements: 1.1, 1.2, 1.5, 1.6, 17.1, 17.6_
  - [x] 4.2 Modelos `Plan`, `Subscription`, `UsageRecord` con sus carpetas y yamls
    - `Plan` con reglas de Requirement 9.1
    - `Subscription` con regla `period_end > period_start`, `belongsTo workspace/plan`
    - `UsageRecord` con `belongsTo subscription`
    - _Requirements: 9.1, 9.2, 9.7, 17.1, 17.6_
  - [x] 4.3 Modelo `AiProvider` con cifrado de `api_key`
    - `$hidden = ['api_key']`, `$jsonable = ['settings']`
    - Mutators `getApiKeyAttribute` (decrypt) / `setApiKeyAttribute` (encrypt vía `Crypt::encrypt`)
    - Validación `driver in:openrouter,openai,anthropic,custom`
    - Hook `saved`/`saving` que desmarca `is_default=false` en otros providers del mismo workspace cuando uno se marca true
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 15.1, 15.2, 15.3, 17.1, 17.6_
  - [x] 4.4 Modelo `MetaAccount` con cifrado de tokens
    - `$hidden = ['access_token','refresh_token']`
    - Mutators `get/setAccessTokenAttribute`, `get/setRefreshTokenAttribute`
    - Método `isTokenExpired(): bool` y `expiresWithinDays(int $days): bool`
    - Validación regex `act_\d+`, currency `size:3`
    - _Requirements: 2.2, 2.3, 2.4, 15.1, 15.2, 15.3, 17.1, 17.6_
  - [x] 4.5 Modelos `Campaign`, `AdSet`, `Ad` con relaciones y `upsertByMetaId`
    - Reglas y enums según design (status enum, objective, etc.)
    - Método estático `upsertByMetaId(array $metaPayload, int $parentId): self` en cada modelo (idempotencia P4)
    - YAMLs `fields.yaml`/`columns.yaml`
    - _Requirements: 3.2, 3.3, 4.2, 17.1, 17.6_
  - [x] 4.6 Modelo `Insight` (sin timestamps)
    - `$timestamps = false`, `$dates = ['date']`
    - Reglas Requirement 4.4
    - Scope `lookback(int $days)` para filtrar por rango de fechas
    - _Requirements: 4.2, 4.4, 4.5, 17.1, 17.6_
  - [x] 4.7 Modelos `AiAnalysis`, `Recommendation`, `AppliedAction`
    - `AiAnalysis`: trait `SoftDelete`, `$jsonable` para `prompt_payload`, `raw_response`, `metrics_snapshot`
    - `Recommendation`: `$jsonable = ['payload','expected_impact']`, `belongsTo ai_analysis`, `hasOne applied_action`
    - `AppliedAction`: `$jsonable = ['before_state','after_state','meta_response']`, scope inmutable (no `update()` desde código del plugin)
    - YAMLs por modelo
    - _Requirements: 6.7, 6.10, 7.12, 8.1, 8.2, 8.3, 8.5, 8.6, 17.1, 17.6_
  - [x]* 4.8 Tests unitarios de modelos
    - Validar reglas, mutators de cifrado, scopes, `upsertByMetaId`
    - _Requirements: 1.2, 2.4, 4.4, 5.2, 6.7, 9.1, 9.2_
  - [x]* 4.9 Property test P6: Confidencialidad de tokens
    - **Property 6: Token Confidentiality**
    - **Validates: Requirements 2.2, 2.3, 5.3, 15.1, 15.3**
    - Generar N MetaAccounts/AiProviders con tokens random; afirmar que `toArray()` jamás contiene los campos cifrados y que el valor en BD difiere del plano
  - [x]* 4.10 Property test P9: Compatibilidad RainLab Builder
    - **Property 9: Builder Compatibility**
    - **Validates: Requirements 17.1, 17.2, 17.3, 17.4**
    - Recorrer `plugins/aero/masterads/models/` y `controllers/` y afirmar la presencia de los yamls obligatorios para cada entidad

- [x] 5. Permisos, navegación y registro de menús
  - [x] 5.1 Implementar `Plugin::registerPermissions()` con los 9 permisos del plugin
    - Tab "Master Ads" en lang.php
    - Permisos: `aero.masterads.access_plugin`, `manage_workspaces`, `manage_meta_accounts`, `access_campaigns`, `run_analysis`, `review_recommendations`, `apply_recommendations`, `manage_ai_providers`, `manage_billing`
    - _Requirements: 12.1, 12.2, 17.7_
  - [x] 5.2 Implementar `Plugin::registerNavigation()` con menú principal y sub-items
    - Menú "Master Ads" con sub-items: Workspaces, Cuentas Meta, Campañas, Recomendaciones, Análisis IA, Proveedores IA, Planes, Suscripciones
    - Cada sub-item protegido por su permiso
    - _Requirements: 12.2, 18.2_

- [x] 6. Excepciones de dominio
  - [x] 6.1 Crear clases `MetaOAuthException`, `MetaApiException`, `MetaApiRateLimitException`, `AiProviderException`, `QuotaExceededException`, `UnsupportedActionTypeException` en `classes/Exceptions/`
    - Heredan de `RuntimeException`; transportan `code` y `context` (array)
    - _Requirements: 2.5, 3.7, 6.9, 7.8, 7.9, 9.4_

- [x] 7. Integración con Meta Graph API
  - [x] 7.1 Implementar `MetaApiClient` (`classes/Meta/MetaApiClient.php`)
    - Constructor recibe `MetaAccount`; usa Guzzle
    - Método `getPaginated(string $endpoint, array $params): iterable` como **generator** que sigue `paging.next`
    - Método `call(string $method, string $endpoint, array $params): array` con backoff exponencial (1, 2, 4, 8, 16 s) en HTTP 429/613, máx. 5 reintentos
    - Tras 5 fallos lanza `MetaApiRateLimitException`
    - Refresh automático del token si `expiresWithinDays(7)` antes de cada llamada
    - _Requirements: 3.1, 3.6, 3.7, 14.1, 14.3, 15.6_
  - [x] 7.2 Implementar `MetaTokenRefresher` (`classes/Meta/MetaTokenRefresher.php`)
    - Método `refresh(MetaAccount $account): void` que llama al endpoint de Meta para extender token long-lived
    - Postcondición: `expires_at > now() + 30 días`
    - En fallo: setear `last_error` y disparar evento `MetaTokenRefreshFailed`
    - _Requirements: 2.7, 15.6_
  - [x] 7.3 Implementar `MetaOAuthService::exchangeCode()` (`classes/Meta/MetaOAuthService.php`)
    - Envuelto en `DB::transaction()` (atomicidad)
    - Intercambia `code` por long-lived token; verifica `?error=...` y retorna error sin persistir
    - Upsert por (`workspace_id`, `meta_act_id`); cifra tokens al persistir
    - Dispara evento `MetaAccountConnected`
    - _Requirements: 2.1, 2.2, 2.5, 2.6, 2.8, 2.9, 11.1, 15.5_
  - [x] 7.4 Wirear el handler real del callback OAuth en `routes.php`
    - Reemplazar el stub de tarea 1.2; redirige a `backend/aero/masterads/metaaccounts/preview/<id>` en éxito; flash error en fallo
    - _Requirements: 11.1, 11.2_
  - [x]* 7.5 Tests unitarios `MetaApiClient` y `MetaTokenRefresher`
    - Mock Guzzle responses; verificar backoff con reloj falso; verificar excepción al 6º intento
    - _Requirements: 3.6, 3.7, 14.3_
  - [x]* 7.6 Property test P7: Atomicidad de OAuth
    - **Property 7: OAuth Atomicity**
    - **Validates: Requirements 2.5, 15.5**
    - Forzar fallos arbitrarios durante `exchangeCode` (red, BD, validación) y afirmar que el estado de BD queda fully_committed o fully_rolled_back, nunca parcial

- [x] 8. Integración con proveedores IA (OpenRouter por defecto)
  - [x] 8.1 Definir contratos `AiProviderInterface` y DTO `AiResponse`
    - `classes/Ai/AiProviderInterface.php`: `complete()`, `model()`, `estimateCost()`
    - `classes/Ai/AiResponse.php`: readonly DTO con `raw`, `parsed`, `promptTokens`, `completionTokens`, `costUsd`, `model`
    - _Requirements: 5.6, 16.2_
  - [x] 8.2 Implementar `OpenRouterClient` (`classes/Ai/OpenRouterClient.php`)
    - Lee `base_url` de `AiProvider.settings` o `OPENROUTER_BASE_URL`
    - Llama `POST /chat/completions` con headers `Authorization`, `HTTP-Referer`, `X-Title`
    - Pasa `temperature=0.2`, `max_tokens=4000`, `response_format` con `json_schema`
    - Lanza `AiProviderException` ante 4xx/5xx, rate-limit o JSON inválido
    - _Requirements: 6.5, 16.2_
  - [x] 8.3 Implementar `PromptBuilder` (`classes/Ai/PromptBuilder.php`)
    - Métodos `system(string $targetType): string` y `user(target, snapshot, derived, options): string`
    - System prompt fija formato JSON con `RECOMMENDATION_SCHEMA`
    - _Requirements: 6.4, 6.5_
  - [x] 8.4 Implementar `ResponseParser` y `RecommendationValidator`
    - `ResponseParser::parse(AiResponse, schema): array` extrae el array `recommendations` y maneja respuestas malformadas
    - `RecommendationValidator::validate(array $rec, $target): bool` valida `payload` contra el schema específico de cada `action_type`
    - _Requirements: 6.6, 6.7_
  - [x] 8.5 Implementar `AiProviderResolver` (`classes/Ai/AiProviderResolver.php`)
    - Estrategia: `force_provider` > provider con `is_default=true` del workspace > primer provider habilitado
    - Lanza error explícito si no hay provider disponible
    - _Requirements: 5.6, 5.7_
  - [x]* 8.6 Tests unitarios para `OpenRouterClient`, `PromptBuilder`, `ResponseParser`
    - Mock HTTP; respuestas válidas/malformadas; verificación de cabeceras
    - _Requirements: 5.5, 6.5, 6.6_
  - [x]* 8.7 Property test P8: Schema de respuesta IA
    - **Property 8: Recommendation Schema**
    - **Validates: Requirements 6.6, 6.7**
    - Generar respuestas IA random con/sin campos requeridos; afirmar que sólo las que cumplen `RECOMMENDATION_SCHEMA` se persisten

- [x] 9. Motor de optimización (Engine)
  - [x] 9.1 Implementar `MetricsAggregator` (`classes/Engine/MetricsAggregator.php`)
    - Método `fold(array &$snapshot, Insight $i): void` agrega impressions/clicks/spend/conversions
    - Método `finalize(array $snapshot): array` calcula CTR, CPC, ROAS y CPA derivados en una sola pasada
    - _Requirements: 6.4, 14.5_
  - [x] 9.2 Implementar `RecommendationEngineInterface` y `RecommendationEngine`
    - Wirear orden: validar target, validar cuota (`PlanLimiter::canRunAnalysis`), validar `≥ 7 días de Insight`, resolver provider, crear `AiAnalysis (running)`, agregar métricas, construir prompt, llamar provider, parsear/validar, persistir Recommendations, registrar `UsageMeter`, disparar `RecommendationGenerated`
    - En fallo: marcar `AiAnalysis.status=failed`, persistir `error_message`, NO consumir cuota, NO generar Recommendations huérfanas
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9, 6.10, 14.5, 16.2, 16.4_
  - [x] 9.3 Implementar `RecommendationApplierInterface` y `RecommendationApplier`
    - Idempotencia: si existe `AppliedAction` con `success=true` para este rec, devolverla sin tocar Meta
    - Snapshot `before_state`; switch por `action_type` (adjust_budget centavos × 100, pause, resume, scale = before×multiplier, change_audience requiere AdSet, change_creative requiere Ad)
    - Persistir `AppliedAction` (success o fail), actualizar `Recommendation.status`, registrar `UsageMeter('applied_action')`, disparar `RecommendationApplied`
    - Envuelto en `DB::transaction()`; lanza `UnsupportedActionTypeException` antes de tocar Meta cuando aplique
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 7.10, 7.11, 7.12, 8.1, 8.2, 15.5, 19.5_
  - [x]* 9.4 Tests unitarios para `RecommendationEngine` y `MetricsAggregator`
    - Caso éxito, sin cuota, sin insights, fallo del LLM
    - _Requirements: 6.2, 6.3, 6.9, 14.5_
  - [x]* 9.5 Property test P1: Idempotencia de aplicación
    - **Property 1: Idempotent Apply**
    - **Validates: Requirements 7.2, 7.12, 19.5**
    - Llamar `apply()` N veces (1..10) sobre la misma Recommendation con HTTP fake; afirmar exactamente 1 llamada efectiva y `count(AppliedAction success=true) ≤ 1`
  - [x]* 9.6 Property test P5: Trazabilidad total
    - **Property 5: Audit Trail Completeness**
    - **Validates: Requirements 7.10, 7.11, 8.1, 8.2**
    - Para toda AppliedAction generada por random apply: `before_state ≠ NULL` y la implicancia `success ⟹ after_state ≠ NULL ∧ rec.status='applied'`, `¬success ⟹ rec.status='failed'`

- [x] 10. Billing, cuotas y aislamiento multi-tenant
  - [x] 10.1 Implementar `PlanLimiter` (`classes/Billing/PlanLimiter.php`)
    - `canRunAnalysis(Workspace $ws): bool` (función pura sobre BD)
    - `canConnectMetaAccount(Workspace $ws): bool` (cap de `max_meta_accounts`)
    - `canAutoApply(Workspace $ws): bool`
    - _Requirements: 9.3, 9.4, 9.5, 9.8, 9.9_
  - [x] 10.2 Implementar `UsageMeter` (`classes/Billing/UsageMeter.php`)
    - `record(Subscription $s, string $metric, int $qty=1): UsageRecord`
    - _Requirements: 9.7_
  - [x] 10.3 Implementar scope global de tenant en modelos
    - Trait `BelongsToTenantScope` aplicado a `MetaAccount`, `Campaign`, `AdSet`, `Ad`, `AiAnalysis`, `Recommendation`, `Subscription`
    - Filtra por `workspace_id ∈ workspacesAccessibles(BackendAuth::getUser())`
    - _Requirements: 1.3, 1.4_
  - [~]* 10.4 Tests unitarios `PlanLimiter` y `UsageMeter`
    - Verificar conteo dentro del período y rechazo cuando supera `max_analyses_month`
    - _Requirements: 9.3, 9.5, 9.7_
  - [~]* 10.5 Property test P2: Aislamiento multi-tenant
    - **Property 2: Tenant Isolation**
    - **Validates: Requirements 1.3, 1.4**
    - Crear N workspaces con recursos arbitrarios; afirmar que `query como user de W1` jamás devuelve recursos de W2
  - [~]* 10.6 Property test P3: Conservación de cuota
    - **Property 3: Quota Enforcement**
    - **Validates: Requirements 6.3, 9.3, 9.4**
    - Disparar M intentos de análisis (M > max_analyses_month); afirmar que `count(UsageRecord en período) ≤ plan.max_analyses_month`

- [x] 11. Jobs asíncronos
  - [x] 11.1 Implementar `SyncMetaAccountJob` (`jobs/SyncMetaAccountJob.php`)
    - Refresh del token si `expiresWithinDays(7)`; sync upsert de campaigns/adsets/ads; encolar `SyncInsightsJob`; actualizar `last_synced_at`; disparar `SyncCompleted`
    - Logs estructurados con `correlation_id` y `meta_account_id`
    - _Requirements: 3.1, 3.2, 3.4, 3.5, 10.1, 10.2, 10.7, 16.1, 19.4_
  - [x] 11.2 Implementar `SyncInsightsJob` (`jobs/SyncInsightsJob.php`)
    - Itera generator de `/insights?level=ad&time_range=...`; upsert idempotente por `(entity_type, entity_id, date)`
    - _Requirements: 4.1, 4.2, 4.3, 10.1, 10.2, 16.1, 19.4_
  - [x] 11.3 Implementar `RunAiAnalysisJob` (`jobs/RunAiAnalysisJob.php`)
    - Delega a `RecommendationEngine::analyze()`
    - En excepción no recuperable: marcar job `failed`, persistir `error_message` en `AiAnalysis`
    - _Requirements: 6.1, 10.1, 10.2, 10.7, 16.1, 16.4_
  - [x] 11.4 Implementar `ApplyRecommendationJob` (`jobs/ApplyRecommendationJob.php`)
    - Delega a `RecommendationApplier::apply()`
    - _Requirements: 7.1, 10.1, 10.2, 10.7, 16.1, 16.4, 19.5_
  - [~]* 11.5 Property test P4: Idempotencia de sync
    - **Property 4: Sync Idempotency**
    - **Validates: Requirements 4.2, 4.3**
    - Ejecutar `SyncInsightsJob` N veces para la misma cuenta+rango; afirmar que `count(Insight)` no cambia tras la 2ª ejecución (igual al de la 1ª)

- [x] 12. Checkpoint - Verificar capa de dominio y jobs
  - Asegurar que todos los tests escritos hasta ahora pasen; preguntar al usuario si surgen dudas.

- [x] 13. Controllers backend (cada uno con yamls Builder)
  - [x] 13.1 `controllers/workspaces/Workspaces.php` + `config_form.yaml` + `config_list.yaml`
    - `implement: FormController, ListController, RelationController`
    - `requiredPermissions = ['aero.masterads.manage_workspaces']`
    - Vista de detalle muestra miembros (RelationController contra `members` con pivot `role`)
    - _Requirements: 1.1, 1.5, 1.6, 12.2, 17.2, 20.1_
  - [x] 13.2 `controllers/metaaccounts/MetaAccounts.php` + yamls
    - Botón "Conectar con Meta" que inicia OAuth con `META_OAUTH_REDIRECT`
    - Acción AJAX `onSyncNow` despacha `SyncMetaAccountJob`
    - `requiredPermissions = ['aero.masterads.manage_meta_accounts']`
    - _Requirements: 2.1, 3.8, 9.5, 10.2, 11.2, 12.2, 12.6, 17.2, 20.1_
  - [x] 13.3 `controllers/campaigns/Campaigns.php` (+ adsets + ads) con yamls
    - Listas filtrables; acción `onAnalyzeNow(int $id)` con `authorize('aero.masterads.run_analysis')` y dispatch a `RunAiAnalysisJob`
    - _Requirements: 6.1, 10.2, 12.2, 12.6, 17.2, 20.1_
  - [x] 13.4 `controllers/adsets/AdSets.php` y `controllers/ads/Ads.php` con yamls
    - Mismo patrón que campaigns
    - _Requirements: 6.1, 12.2, 17.2, 20.1_
  - [x] 13.5 `controllers/recommendations/Recommendations.php` + yamls
    - Vista detalle muestra `before_state` vs `after_state`, `rationale`, `payload`
    - Acciones `onApprove`, `onReject`, `onApplyNow` (la última con `authorize('apply_recommendations')` y dispatch a `ApplyRecommendationJob`)
    - _Requirements: 7.1, 8.4, 10.2, 12.2, 12.6, 17.2, 20.1_
  - [x] 13.6 `controllers/aianalyses/AiAnalyses.php` + yamls
    - Lista con columnas filtrables `status`, `tokens_used`, `cost_usd`, `provider.model`
    - _Requirements: 6.10, 8.5, 16.2, 16.3, 17.2, 20.1_
  - [x] 13.7 `controllers/aiproviders/AiProviders.php` + yamls
    - Form con `driver`, `model`, `api_key` (password type), `is_default`, `settings` (repeater)
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 12.2, 17.2, 20.1_
  - [x] 13.8 `controllers/plans/Plans.php` y `controllers/subscriptions/Subscriptions.php` + yamls
    - `requiredPermissions = ['aero.masterads.manage_billing']`
    - _Requirements: 9.1, 9.2, 12.2, 17.2, 20.1_
  - [x]* 13.9 Tests funcionales backend (smoke)
    - Verificar 403 cuando falta permiso; verificar 200 cuando se cumple; verificar dispatch de jobs vs ejecución inline
    - _Requirements: 12.6, 14.2_

- [x] 14. Comandos artisan
  - [x] 14.1 `console/SyncAllCommand.php` — `masterads:sync-all`
    - Itera `MetaAccount` activos y dispatcha `SyncMetaAccountJob`
    - _Requirements: 3.8, 10.3_
  - [x] 14.2 `console/AnalyzeCommand.php` — `masterads:analyze [--auto]`
    - Sin flag: requiere `--target-type` y `--target-id` y dispatcha `RunAiAnalysisJob`
    - Con `--auto`: itera Workspaces marcados como auto-analyze y dispatcha por cada target candidato
    - _Requirements: 6.11, 10.3_
  - [x] 14.3 `console/RotateTokensCommand.php` — `masterads:rotate-tokens`
    - Llama `MetaTokenRefresher::refresh()` para todos los `MetaAccount` con `expiresWithinDays(7)`
    - _Requirements: 2.7, 10.3, 15.6_
  - [x] 14.4 Registrar los comandos en `Plugin::register()` y configurar `registerSchedule()`
    - `sync-all` cada 4 h con `withoutOverlapping()->onOneServer()`
    - `rotate-tokens` diariamente 03:00 con `withoutOverlapping()`
    - `analyze --auto` diariamente 06:00 con `withoutOverlapping()`
    - _Requirements: 10.4, 10.5, 10.6, 19.2, 19.3_

- [x] 15. Eventos, listeners y observers
  - [x] 15.1 Definir clases de evento en `events/`
    - `MetaAccountConnected`, `SyncCompleted`, `RecommendationGenerated`, `RecommendationApplied`, `MetaTokenRefreshFailed`
    - Cada uno con propiedades públicas readonly del payload
    - _Requirements: 2.8, 3.4, 13.1, 13.2, 13.3, 13.4_
  - [x] 15.2 Implementar `listeners/NotifyRecommendationListener.php`
    - Suscrito a `RecommendationGenerated`; notifica a miembros del Workspace (sólo logs/Flash en MVP)
    - _Requirements: 13.5_
  - [x] 15.3 Implementar `observers/RecommendationObserver.php`
    - Hook `created`: si `Plan.auto_apply_allowed=true` y workspace lo permite, dispatch `ApplyRecommendationJob`
    - Hook `created`: rechazar auto-apply si `auto_apply_allowed=false`
    - _Requirements: 9.8, 9.9_
  - [x] 15.4 Implementar `observers/SubscriptionObserver.php`
    - Hook `updated`: al cambiar de período, recalcular cuotas efectivas (sin borrar UsageRecords históricos)
    - _Requirements: 9.6_
  - [x] 15.5 Registrar listeners y observers en `Plugin::boot()`
    - `Event::listen()` para listeners; `Model::observe()` para observers
    - _Requirements: 13.5, 9.6, 9.8_

- [x] 16. Documentación y entregables
  - [x] 16.1 Crear `plugins/aero/masterads/README.md`
    - Secciones: instalación, variables de entorno requeridas, comandos artisan, schedule, matriz de permisos, modelo de datos resumido
    - Diagrama Mermaid del flujo OAuth → Sync → Análisis → Apply (referenciar diseño)
    - _Requirements: 11.1, 12.1, 17.7, 18.1_
  - [x] 16.2 Actualizar `lang/es/lang.php` con todas las cadenas finales
    - Cubre todas las keys referenciadas por yamls y `registerPermissions`
    - _Requirements: 18.1, 18.2, 18.3_

- [x] 17. Checkpoint final - Verificar suite completa
  - Ejecutar la suite completa (unit + integration + property-based de las 9 propiedades). Asegurarse de que todos los tests pasen; preguntar al usuario si surgen dudas.

---

## Notes

- Tareas marcadas con `*` son opcionales (tests). El **core de implementación NO debe saltarse**, pero los tests pueden diferirse para un MVP express. Aun así, **los 9 property tests cubren las 9 propiedades de corrección de `design.md` y deben implementarse antes de salir a producción**.
- Cada tarea referencia explícitamente las cláusulas de requisitos que satisface mediante `_Requirements: X.Y_`.
- Los checkpoints (tareas 3, 12, 17) son momentos de validación incremental; no agregan código nuevo, sólo aseguran consistencia.
- El orden bottom-up garantiza que cada capa se prueba antes de que la siguiente la consuma: datos → dominio → integraciones → motor → orquestación (jobs/controllers/comandos) → eventos → tests → docs.
- Mapa de propiedades a tareas:
  - **P1** Idempotencia apply → 9.5
  - **P2** Aislamiento multi-tenant → 10.5
  - **P3** Conservación de cuota → 10.6
  - **P4** Idempotencia de sync → 11.5
  - **P5** Trazabilidad total → 9.6
  - **P6** Confidencialidad de tokens → 4.9
  - **P7** Atomicidad OAuth → 7.6
  - **P8** Schema de respuesta IA → 8.7
  - **P9** Compatibilidad RainLab Builder → 4.10

---

## Task Dependency Graph

### Visualización (Mermaid)

```mermaid
graph LR
    subgraph W0["Wave 0: Scaffold"]
        T11["1.1 Plugin.php + composer"]
        T12["1.2 routes.php stub"]
        T13["1.3 lang/es"]
        T14["1.4 .env + services"]
        T15["1.5 version.yaml"]
    end
    subgraph W1["Wave 1: Migraciones"]
        T21["2.1 workspaces"]
        T22["2.2 billing"]
        T23["2.3 ai_providers"]
        T24["2.4 meta_accounts"]
        T25["2.5 campaigns/adsets/ads"]
        T26["2.6 insights"]
        T27["2.7 ai_analyses/rec/applied"]
    end
    subgraph W2["Wave 2: Excepciones + Modelos base"]
        T61["6.1 Exceptions"]
        T41["4.1 Workspace"]
        T42["4.2 Plan/Sub/Usage"]
        T43["4.3 AiProvider"]
        T44["4.4 MetaAccount"]
        T45["4.5 Campaign/AdSet/Ad"]
        T46["4.6 Insight"]
        T47["4.7 AiAnalysis/Rec/Applied"]
    end
    subgraph W3["Wave 3: Permisos + Tests modelos"]
        T51["5.1 Permissions"]
        T52["5.2 Navigation"]
        T48["4.8* unit"]
        T49["4.9* P6"]
        T410["4.10* P9"]
    end
    subgraph W4["Wave 4: Integraciones externas"]
        T71["7.1 MetaApiClient"]
        T72["7.2 TokenRefresher"]
        T81["8.1 AiProvider iface"]
        T82["8.2 OpenRouterClient"]
        T83["8.3 PromptBuilder"]
        T84["8.4 Parser/Validator"]
        T85["8.5 Resolver"]
        T103["10.3 Tenant scope"]
    end
    subgraph W5["Wave 5: OAuth + Engine + Billing"]
        T73["7.3 OAuthService"]
        T74["7.4 routes wire"]
        T101["10.1 PlanLimiter"]
        T102["10.2 UsageMeter"]
        T91["9.1 MetricsAggregator"]
        T75["7.5* tests Meta"]
        T76["7.6* P7"]
        T86["8.6* tests AI"]
        T87["8.7* P8"]
    end
    subgraph W6["Wave 6: Engine core + tests billing"]
        T92["9.2 RecommendationEngine"]
        T93["9.3 RecommendationApplier"]
        T104["10.4* tests billing"]
        T105["10.5* P2"]
        T106["10.6* P3"]
    end
    subgraph W7["Wave 7: Jobs + tests engine"]
        T111["11.1 SyncMetaAccountJob"]
        T112["11.2 SyncInsightsJob"]
        T113["11.3 RunAiAnalysisJob"]
        T114["11.4 ApplyRecommendationJob"]
        T94["9.4* tests engine"]
        T95["9.5* P1"]
        T96["9.6* P5"]
    end
    subgraph W8["Wave 8: Controllers + tests jobs"]
        T131["13.1 Workspaces"]
        T132["13.2 MetaAccounts"]
        T133["13.3 Campaigns"]
        T134["13.4 AdSets/Ads"]
        T135["13.5 Recommendations"]
        T136["13.6 AiAnalyses"]
        T137["13.7 AiProviders"]
        T138["13.8 Plans/Subs"]
        T115["11.5* P4"]
    end
    subgraph W9["Wave 9: Comandos + tests controllers"]
        T141["14.1 sync-all"]
        T142["14.2 analyze"]
        T143["14.3 rotate-tokens"]
        T139["13.9* tests bk"]
    end
    subgraph W10["Wave 10: Eventos/Schedule"]
        T144["14.4 Schedule"]
        T151["15.1 Events"]
        T152["15.2 Listener"]
        T153["15.3 RecObserver"]
        T154["15.4 SubObserver"]
    end
    subgraph W11["Wave 11: Wiring final + Docs"]
        T155["15.5 boot wiring"]
        T161["16.1 README"]
        T162["16.2 lang.php final"]
    end

    W0 --> W1 --> W2 --> W3 --> W4 --> W5 --> W6 --> W7 --> W8 --> W9 --> W10 --> W11
```

### Definición ejecutable (JSON)

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3", "1.4", "1.5"] },
    { "id": 1, "tasks": ["2.1", "2.2", "2.3", "2.4", "2.5", "2.6", "2.7"] },
    { "id": 2, "tasks": ["6.1", "4.1", "4.2", "4.3", "4.4", "4.5", "4.6", "4.7"] },
    { "id": 3, "tasks": ["5.1", "5.2", "4.8", "4.9", "4.10"] },
    { "id": 4, "tasks": ["7.1", "7.2", "8.1", "8.2", "8.3", "8.4", "8.5", "10.3"] },
    { "id": 5, "tasks": ["7.3", "7.4", "10.1", "10.2", "9.1", "7.5", "7.6", "8.6", "8.7"] },
    { "id": 6, "tasks": ["9.2", "9.3", "10.4", "10.5", "10.6"] },
    { "id": 7, "tasks": ["11.1", "11.2", "11.3", "11.4", "9.4", "9.5", "9.6"] },
    { "id": 8, "tasks": ["13.1", "13.2", "13.3", "13.4", "13.5", "13.6", "13.7", "13.8", "11.5"] },
    { "id": 9, "tasks": ["14.1", "14.2", "14.3", "13.9"] },
    { "id": 10, "tasks": ["14.4", "15.1", "15.2", "15.3", "15.4"] },
    { "id": 11, "tasks": ["15.5", "16.1", "16.2"] }
  ]
}
```
