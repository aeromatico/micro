# Requirements Document

## Introduction

Master Ads es un plugin OctoberCMS 4 (`Aero\MasterAds`) que actúa como un SaaS multi-tenant para optimizar campañas publicitarias en Meta Ads asistido por IA. Esta versión es un **MVP backend-only** (sin frontend público) totalmente compatible con RainLab Builder. El sistema sincroniza cuentas, campañas, adsets y ads desde la Meta Graph API; ejecuta análisis con LLMs (OpenRouter + Claude por defecto); produce recomendaciones aplicables (cambios de presupuesto, segmentación, pausas, escalados); y permite aplicarlas a Meta con trazabilidad completa.

Este documento describe los requisitos funcionales y no-funcionales derivados del diseño técnico aprobado en `design.md`.

## Glossary

- **Master_Ads**: el plugin completo `Aero\MasterAds` y todos sus componentes (modelos, controladores, jobs, comandos, eventos) actuando como un único sistema.
- **Workspace**: tenant aislado del SaaS que agrupa usuarios, cuentas Meta, suscripciones, análisis y recomendaciones de un cliente.
- **Workspace_Member**: Backend_User asociado a un Workspace mediante la tabla pivote `aero_masterads_workspace_user` con un rol (`owner`, `admin`, `viewer`).
- **Workspace_Owner**: miembro con rol `owner`. Único responsable inicial del workspace y dueño implícito del registro `Workspace.owner_id`.
- **Workspace_Admin**: miembro con rol `admin`. Gestiona conexiones Meta, ejecuta análisis y aplica recomendaciones.
- **Workspace_Viewer**: miembro con rol `viewer`. Acceso de sólo lectura a campañas, métricas y recomendaciones.
- **Backend_User**: usuario del Backend de OctoberCMS (modelo `Backend\Models\User`).
- **Meta_Account**: cuenta publicitaria Meta (`act_<id>`) conectada a un Workspace vía OAuth.
- **Campaign / Ad_Set / Ad**: jerarquía de entidades sincronizadas desde Meta.
- **Insight**: registro de métricas diarias (impresiones, clicks, gasto, conversiones) para una entidad sincronizada.
- **Ai_Provider**: configuración de un proveedor LLM (driver, modelo, API key, settings).
- **Ai_Analysis**: una corrida de análisis IA sobre un target (campaign|adset|ad). Agrupa N Recommendation hijas.
- **Recommendation**: sugerencia atómica generada por la IA, con `action_type`, `payload` y `rationale`.
- **Applied_Action**: registro inmutable de auditoría que documenta la aplicación efectiva de una Recommendation a Meta.
- **Plan**: definición de un nivel de suscripción con cuotas (`max_meta_accounts`, `max_analyses_month`, `auto_apply_allowed`).
- **Subscription**: vínculo activo entre Workspace y Plan, con `period_start`, `period_end` y `status`.
- **Usage_Record**: contador discreto de consumo por suscripción y métrica (`analysis`, `sync`, `applied_action`).
- **Meta_Api_Client**: wrapper HTTP de la Graph API de Meta con paginación, refresh de token y backoff.
- **Meta_OAuth_Service**: servicio que ejecuta el authorization code flow contra Meta.
- **Recommendation_Engine**: orquestador que ejecuta `Ai_Analysis` y persiste `Recommendation`s.
- **Recommendation_Applier**: servicio idempotente que aplica una `Recommendation` aprobada a Meta.
- **Plan_Limiter**: servicio que verifica cuotas antes de iniciar operaciones contables.
- **Usage_Meter**: servicio que registra `Usage_Record` tras cada operación contable.
- **Builder**: plugin RainLab Builder, generador de plantillas backend de OctoberCMS.
- **Token**: el `access_token` long-lived emitido por Meta para una `Meta_Account`.
- **Graph_API**: la Meta Graph API v19+ contra la que se sincronizan datos y se aplican cambios.

---

## Requirements

### Requirement 1: Gestión multi-tenant de Workspaces

**User Story:** Como Workspace_Owner, quiero crear y gestionar workspaces aislados, para separar los recursos publicitarios de cada cliente o proyecto sin riesgo de contaminación cruzada entre tenants.

#### Acceptance Criteria

1. WHEN un Backend_User con permiso `aero.masterads.manage_workspaces` envía el formulario de creación de Workspace con `name` (≤ 120 caracteres) y `slug` (alpha_dash) válidos, THE Master_Ads SHALL persistir un nuevo Workspace asignando ese Backend_User como `owner_id`.
2. THE Master_Ads SHALL exigir que `Workspace.slug` sea único en `aero_masterads_workspaces` y respete el patrón `alpha_dash`.
3. IF un Backend_User intenta acceder a un recurso (Meta_Account, Campaign, Ad_Set, Ad, Insight, Ai_Analysis, Recommendation, Subscription) cuyo Workspace no incluye al usuario como miembro, THEN THE Master_Ads SHALL denegar el acceso devolviendo HTTP 403 sin filtrar datos del recurso.
4. WHEN se renderiza cualquier listado del plugin en el backend, THE Master_Ads SHALL filtrar los registros para devolver únicamente los pertenecientes a Workspaces en los que el Backend_User actual es Workspace_Member.
5. WHEN un Workspace_Owner agrega o elimina un Workspace_Member, THE Master_Ads SHALL persistir el cambio en la tabla pivote `aero_masterads_workspace_user` con el rol indicado (`owner`, `admin` o `viewer`).
6. THE Master_Ads SHALL impedir eliminar un Workspace que tenga Subscriptions activas o Meta_Accounts conectados, devolviendo un mensaje de validación al usuario.

### Requirement 2: Conexión OAuth a cuentas Meta

**User Story:** Como Workspace_Admin, quiero conectar una cuenta publicitaria de Meta vía OAuth, para que el sistema pueda sincronizar campañas y ejecutar acciones en mi nombre con un long-lived access_token.

#### Acceptance Criteria

1. WHEN un Workspace_Admin inicia el flujo OAuth y Meta redirige al callback con un parámetro `code` válido, THE Meta_OAuth_Service SHALL intercambiar el `code` por un long-lived access_token y persistir un Meta_Account asociado al Workspace activo.
2. WHEN se persiste un Meta_Account, THE Master_Ads SHALL cifrar el `access_token` y el `refresh_token` mediante `Crypt::encrypt` antes de almacenarlos en sus columnas.
3. THE Master_Ads SHALL ocultar `access_token` y `refresh_token` en toda serialización JSON del modelo Meta_Account mediante el atributo `$hidden`.
4. THE Master_Ads SHALL validar que `Meta_Account.meta_act_id` cumpla el patrón `act_\d+` y que `Meta_Account.currency` tenga exactamente 3 caracteres.
5. IF Meta devuelve un error durante el intercambio del `code`, THEN THE Master_Ads SHALL lanzar `MetaOAuthException` y revertir cualquier cambio parcial mediante una transacción atómica (no quedan filas huérfanas).
6. WHEN el callback OAuth ya tiene un Meta_Account con el mismo `meta_act_id` y `workspace_id`, THE Master_Ads SHALL actualizar el `access_token` y `expires_at` del registro existente en lugar de crear un duplicado.
7. WHEN un access_token está a menos de 7 días de su `expires_at`, THE Meta_Token_Refresher SHALL refrescarlo automáticamente al iniciar cualquier operación contra la Graph_API y SHALL garantizar que tras el refresh `expires_at > now() + 30 días`.
8. WHEN se completa exitosamente la conexión de un Meta_Account, THE Master_Ads SHALL despachar el evento `MetaAccountConnected` con el Meta_Account adjunto.
9. IF la ruta del callback recibe `?error=...` desde Meta en lugar de `?code=...`, THEN THE Master_Ads SHALL redirigir al backend con un Flash message de error y NO crear ningún Meta_Account.

### Requirement 3: Sincronización incremental de entidades Meta

**User Story:** Como Workspace_Admin, quiero que el plugin sincronice automáticamente mis campañas, adsets y ads desde Meta, para tener una réplica local consultable y auditable sin intervención manual.

#### Acceptance Criteria

1. WHEN se ejecuta `SyncMetaAccountJob` para un Meta_Account, THE Master_Ads SHALL recorrer secuencialmente los endpoints `/<act_id>/campaigns`, `/<act_id>/adsets` y `/<act_id>/ads` siguiendo cursores `paging.next` hasta agotar la paginación.
2. WHEN se procesa cada elemento de la respuesta, THE Master_Ads SHALL hacer upsert por `meta_id` sobre las tablas `aero_masterads_campaigns`, `aero_masterads_ad_sets` y `aero_masterads_ads` sin crear duplicados.
3. THE Master_Ads SHALL validar que cada Campaign tenga `meta_id` único, `name` (≤ 255), `objective` no vacío y `status ∈ {ACTIVE, PAUSED, ARCHIVED, DELETED}`.
4. WHEN finaliza exitosamente el sync de entidades, THE Master_Ads SHALL actualizar `Meta_Account.last_synced_at` con `now()` y SHALL despachar el evento `SyncCompleted`.
5. WHEN finaliza exitosamente el sync de entidades, THE Master_Ads SHALL encolar un `SyncInsightsJob` para el período `[last_synced_at, today]`.
6. IF la Graph_API devuelve un código 429 o 613 (rate-limit), THEN THE Meta_Api_Client SHALL aplicar backoff exponencial con esperas de 1, 2, 4, 8 y 16 segundos hasta un máximo de 5 reintentos.
7. IF tras 5 reintentos consecutivos persiste el rate-limit, THEN THE Meta_Api_Client SHALL lanzar `MetaApiRateLimitException` y dejar el job en estado `failed`.
8. THE Master_Ads SHALL exponer el comando `php artisan masterads:sync-all` que dispara `SyncMetaAccountJob` para todos los Meta_Account activos del sistema.

### Requirement 4: Sincronización idempotente de métricas (Insights)

**User Story:** Como Workspace_Admin, quiero que las métricas diarias se sincronicen sin duplicar registros, para tener un histórico time-series confiable que alimente al motor de análisis IA.

#### Acceptance Criteria

1. WHEN se ejecuta `SyncInsightsJob` para un Meta_Account y un rango de fechas, THE Master_Ads SHALL invocar `/<act_id>/insights?level=ad&time_range=...` con paginación y procesar cada página vía generator.
2. THE Master_Ads SHALL persistir cada Insight con la tupla (`entity_type`, `entity_id`, `date`) garantizada como única mediante un índice único en BD.
3. IF se intenta insertar un Insight cuya tupla (`entity_type`, `entity_id`, `date`) ya existe, THEN THE Master_Ads SHALL hacer upsert (no duplicar) preservando idempotencia.
4. THE Master_Ads SHALL validar que `entity_type ∈ {campaign, adset, ad}`, que `impressions`, `clicks` y `conversions` sean enteros ≥ 0 y que `spend` sea numérico ≥ 0.
5. THE Master_Ads SHALL almacenar Insights con `timestamps = false`, considerando `date` como el único eje temporal del registro.

### Requirement 5: Configuración de proveedores IA

**User Story:** Como Workspace_Admin, quiero configurar uno o más proveedores LLM (OpenRouter, OpenAI, Anthropic, custom) y elegir uno por defecto, para flexibilizar costo, latencia y calidad de los análisis.

#### Acceptance Criteria

1. WHEN un Backend_User con permiso `aero.masterads.manage_ai_providers` crea un Ai_Provider con `name` (≤ 120), `driver`, `model` y `api_key` válidos, THE Master_Ads SHALL persistir el registro.
2. THE Master_Ads SHALL validar que `Ai_Provider.driver ∈ {openrouter, openai, anthropic, custom}`.
3. THE Master_Ads SHALL ocultar el campo `api_key` en toda serialización JSON del Ai_Provider mediante el atributo `$hidden`.
4. WHEN se marca un Ai_Provider con `is_default = true` dentro de un Workspace, THE Master_Ads SHALL marcar como `false` el flag `is_default` de cualquier otro Ai_Provider del mismo Workspace para mantener un único default.
5. THE Master_Ads SHALL aceptar en el campo JSON `Ai_Provider.settings` los parámetros opcionales `temperature`, `max_tokens`, `base_url`, `http_referer` y `x_title`.
6. WHEN se ejecuta un análisis sin la opción `force_provider`, THE Recommendation_Engine SHALL resolver el Ai_Provider con `is_default = true` del Workspace activo.
7. IF no existe ningún Ai_Provider habilitado para el Workspace al momento de iniciar un análisis, THEN THE Recommendation_Engine SHALL rechazar el análisis con un error explícito y NO crear el Ai_Analysis.

### Requirement 6: Generación de análisis IA y recomendaciones

**User Story:** Como Workspace_Admin, quiero solicitar un análisis IA para una Campaign, AdSet o Ad, para obtener recomendaciones accionables basadas en mis datos históricos y mi contexto de cuenta.

#### Acceptance Criteria

1. WHEN un Backend_User con permiso `aero.masterads.run_analysis` invoca el motor con `targetType ∈ {campaign, adset, ad}` y un `targetId` existente, THE Recommendation_Engine SHALL crear exactamente un Ai_Analysis con `status = running`.
2. IF el target no posee al menos 7 días de Insight registrados, THEN THE Recommendation_Engine SHALL rechazar el análisis con un error explícito y NO crear el Ai_Analysis ni consumir cuota.
3. IF `Plan_Limiter::canRunAnalysis(workspace)` devuelve `false`, THEN THE Recommendation_Engine SHALL lanzar `QuotaExceededException` y NO crear el Ai_Analysis ni consumir cuota.
4. WHEN se construye el prompt, THE Recommendation_Engine SHALL agregar las métricas del período `lookback_days` (por defecto 14) y persistir el agregado en `Ai_Analysis.metrics_snapshot` para reproducibilidad.
5. WHEN se invoca al Ai_Provider, THE Recommendation_Engine SHALL pasar `temperature = 0.2`, `max_tokens = 4000` y un `json_schema` que restrinja la estructura de la respuesta.
6. WHEN el Ai_Provider devuelve una respuesta exitosa, THE Recommendation_Engine SHALL parsearla, descartar Recommendations que no validen contra el schema de su `action_type`, y persistir las restantes con `status = pending`.
7. THE Master_Ads SHALL validar que cada Recommendation tenga `action_type ∈ {adjust_budget, pause, resume, scale, change_audience, change_creative}`, `severity ∈ {low, medium, high, critical}`, `status ∈ {pending, approved, rejected, applied, failed}` y `rationale` no vacío.
8. WHEN el análisis termina exitosamente, THE Master_Ads SHALL marcar `Ai_Analysis.status = success`, registrar un Usage_Record con `metric = analysis, qty = 1` y despachar el evento `RecommendationGenerated`.
9. IF la llamada al Ai_Provider falla con `AiProviderException`, THEN THE Master_Ads SHALL marcar `Ai_Analysis.status = failed`, persistir `error_message`, NO registrar Usage_Record y NO crear Recommendations huérfanas.
10. THE Master_Ads SHALL persistir en cada Ai_Analysis los campos `prompt_payload` (JSON con `system` y `user`) y `raw_response` (JSON con la respuesta cruda del LLM).
11. THE Master_Ads SHALL exponer el comando `php artisan masterads:analyze` para disparar análisis desde CLI, con flag opcional `--auto` que procesa todos los Workspaces marcados como auto-analyze.

### Requirement 7: Aplicación idempotente de recomendaciones a Meta

**User Story:** Como Workspace_Admin con permiso de aplicación, quiero aprobar y aplicar una Recommendation a Meta, para que el cambio se ejecute sin riesgo de doble aplicación y con auditoría completa del antes y el después.

#### Acceptance Criteria

1. WHEN un Backend_User cambia el estado de una Recommendation a `approved`, THE Master_Ads SHALL permitir su aplicación únicamente si el usuario posee el permiso `aero.masterads.apply_recommendations` en el Workspace del target.
2. WHEN `Recommendation_Applier::apply(rec, userId)` se invoca y ya existe un Applied_Action con `recommendation_id = rec.id` y `success = true`, THE Master_Ads SHALL devolver el Applied_Action existente sin volver a llamar a la Graph_API.
3. WHEN se inicia la aplicación, THE Master_Ads SHALL leer el estado previo del recurso desde la Graph_API y persistirlo en `Applied_Action.before_state`.
4. WHEN `Recommendation.action_type = adjust_budget`, THE Master_Ads SHALL hacer POST a `/<meta_id>` con `daily_budget = payload.daily_budget * 100` (centavos).
5. WHEN `Recommendation.action_type = pause`, THE Master_Ads SHALL hacer POST a `/<meta_id>` con `status = PAUSED`.
6. WHEN `Recommendation.action_type = resume`, THE Master_Ads SHALL hacer POST a `/<meta_id>` con `status = ACTIVE`.
7. WHEN `Recommendation.action_type = scale`, THE Master_Ads SHALL calcular `daily_budget = before_state.daily_budget * payload.multiplier` y hacer POST a `/<meta_id>` con ese nuevo presupuesto.
8. IF `Recommendation.action_type = change_audience` y el target no es un Ad_Set, THEN THE Master_Ads SHALL lanzar `UnsupportedActionTypeException` antes de tocar la Graph_API.
9. IF `Recommendation.action_type = change_creative` y el target no es un Ad, THEN THE Master_Ads SHALL lanzar `UnsupportedActionTypeException` antes de tocar la Graph_API.
10. WHEN la llamada a Meta finaliza exitosamente, THE Master_Ads SHALL leer el estado posterior, persistir un Applied_Action con `success = true`, `before_state`, `after_state` y `meta_response` poblados, marcar `Recommendation.status = applied`, registrar un Usage_Record con `metric = applied_action` y despachar `RecommendationApplied`.
11. IF la llamada a Meta falla con `MetaApiException`, THEN THE Master_Ads SHALL persistir un Applied_Action con `success = false` y `meta_response.error` poblado, marcar `Recommendation.status = failed` y propagar la excepción al llamador.
12. THE Master_Ads SHALL garantizar mediante un índice único parcial sobre (`recommendation_id`, `success`) que existe a lo sumo un Applied_Action con `success = true` por Recommendation.

### Requirement 8: Auditoría y trazabilidad

**User Story:** Como Workspace_Owner, quiero un audit trail inmutable de todo cambio aplicado a Meta y de cada análisis IA ejecutado, para reconstruir qué cambió, cuándo, quién lo aprobó y cuál fue el costo del análisis.

#### Acceptance Criteria

1. THE Master_Ads SHALL registrar en cada Applied_Action los campos `recommendation_id`, `applied_by` (FK a `backend_users`), `success`, `before_state`, `after_state`, `meta_response` y timestamps automáticos.
2. THE Master_Ads SHALL almacenar `Applied_Action.before_state`, `Applied_Action.after_state` y `Applied_Action.meta_response` como columnas JSON declaradas en `$jsonable`.
3. WHILE existe una Recommendation en estado `applied`, THE Master_Ads SHALL preservar su Applied_Action y NO permitir su modificación o eliminación desde la lógica del plugin (registro append-only).
4. THE Master_Ads SHALL exponer en el controller `Recommendations` una vista de detalle que muestre `before_state` vs `after_state` para cada Applied_Action asociada.
5. THE Master_Ads SHALL persistir en cada Ai_Analysis los campos `prompt_payload`, `raw_response`, `metrics_snapshot`, `tokens_used` y `cost_usd` para reproducibilidad y control de costos.
6. THE Master_Ads SHALL aplicar `SoftDelete` al modelo Ai_Analysis para preservar el historial incluso ante operaciones de borrado lógico.

### Requirement 9: Planes, suscripciones y cuotas

**User Story:** Como administrador del SaaS, quiero definir planes con cuotas y vincularlos a Workspaces vía Subscriptions, para monetizar el servicio y controlar el consumo de cada tenant.

#### Acceptance Criteria

1. THE Master_Ads SHALL permitir crear Plans con los campos: `code` (alpha_dash, único en `aero_masterads_plans`), `monthly_price` (numeric ≥ 0), `max_meta_accounts` (int ≥ 1), `max_analyses_month` (int ≥ 1) y `auto_apply_allowed` (boolean).
2. THE Master_Ads SHALL validar que cada Subscription tenga `status ∈ {active, past_due, canceled, trialing}` y que `period_end > period_start`.
3. WHEN se intenta iniciar un análisis para un Workspace, THE Plan_Limiter SHALL devolver `true` si y sólo si:
   - existe una Subscription con `status ∈ {active, trialing}` para el Workspace, AND
   - `count(Usage_Record con metric = analysis y recorded_at ∈ [period_start, period_end]) < plan.max_analyses_month`.
4. IF `Plan_Limiter::canRunAnalysis()` devuelve `false`, THEN THE Master_Ads SHALL prevenir el inicio del análisis y devolver un error claro al cliente del API.
5. WHEN se intenta conectar un nuevo Meta_Account a un Workspace, THE Master_Ads SHALL rechazar la conexión si `count(Meta_Account actuales del workspace) ≥ plan.max_meta_accounts`.
6. WHEN una Subscription cambia a un nuevo período (renovación), THE Subscription_Observer SHALL recalcular cuotas efectivas a partir de `Usage_Record.recorded_at` sin borrar registros históricos.
7. WHEN se completa una operación contable (`analysis`, `sync`, `applied_action`), THE Usage_Meter SHALL crear un Usage_Record con `subscription_id`, `metric`, `qty ≥ 1` y `recorded_at = now()`.
8. WHERE el Plan asociado al Workspace tiene `auto_apply_allowed = true`, THE Recommendation_Observer SHALL poder auto-aplicar Recommendations recién generadas según política del Workspace.
9. WHERE el Plan asociado al Workspace tiene `auto_apply_allowed = false`, THE Master_Ads SHALL rechazar cualquier intento de auto-aplicación y exigir aprobación manual.

### Requirement 10: Programación de jobs y comandos CLI

**User Story:** Como operador del sistema, quiero que las tareas costosas se ejecuten asincrónicamente y de forma programada, para no bloquear el backend ni saturar la Graph_API de Meta.

#### Acceptance Criteria

1. THE Master_Ads SHALL despachar a la queue Redis los jobs `SyncMetaAccountJob`, `SyncInsightsJob`, `RunAiAnalysisJob` y `ApplyRecommendationJob`.
2. WHEN un controller backend invoca una operación de larga duración (sync, analysis, apply), THE Master_Ads SHALL devolver respuesta HTTP inmediata al cliente y delegar el trabajo a la queue.
3. THE Master_Ads SHALL exponer los comandos artisan: `masterads:sync-all`, `masterads:analyze` (con flag `--auto`) y `masterads:rotate-tokens`.
4. WHILE el scheduler de OctoberCMS está activo, THE Master_Ads SHALL ejecutar `masterads:sync-all` cada 4 horas con `withoutOverlapping()` y `onOneServer()`.
5. WHILE el scheduler de OctoberCMS está activo, THE Master_Ads SHALL ejecutar `masterads:rotate-tokens` diariamente a las 03:00 con `withoutOverlapping()`.
6. WHILE el scheduler de OctoberCMS está activo, THE Master_Ads SHALL ejecutar `masterads:analyze --auto` diariamente a las 06:00 con `withoutOverlapping()`.
7. IF un job lanza una excepción no recuperable, THEN THE Master_Ads SHALL marcar el job como `failed` en la cola sin reintentos infinitos y persistir el `error_message` en el registro de dominio asociado.

### Requirement 11: Rutas y callback OAuth

**User Story:** Como integrador, quiero rutas dedicadas para el callback OAuth de Meta, para soportar el ciclo completo de conexión sin acoplar al backend de OctoberCMS.

#### Acceptance Criteria

1. THE Master_Ads SHALL declarar en `routes.php` la ruta `GET /aero/masterads/oauth/meta/callback` que invoca `Meta_OAuth_Service::exchangeCode`.
2. WHERE el callback OAuth ejecuta exitosamente, THE Master_Ads SHALL redirigir al Backend_User a la pantalla de detalle del Meta_Account recién creado (`backend/aero/masterads/metaaccounts/preview/<id>`).
3. THE Master_Ads SHALL exigir CSRF en todos los formularios backend del plugin conforme al estándar de OctoberCMS.

### Requirement 12: Permisos y matriz de roles

**User Story:** Como Workspace_Owner, quiero que cada acción del plugin esté protegida por un permiso explícito, para asignar capacidades granularmente a cada Backend_User dentro de mi Workspace.

#### Acceptance Criteria

1. THE Master_Ads SHALL registrar en `registerPermissions()` los siguientes permisos bajo el tab `Master Ads`:
   - `aero.masterads.access_plugin`
   - `aero.masterads.manage_workspaces`
   - `aero.masterads.manage_meta_accounts`
   - `aero.masterads.access_campaigns`
   - `aero.masterads.run_analysis`
   - `aero.masterads.review_recommendations`
   - `aero.masterads.apply_recommendations`
   - `aero.masterads.manage_ai_providers`
   - `aero.masterads.manage_billing`
2. WHEN un Backend_User accede a cualquier controller del plugin, THE Master_Ads SHALL exigir como mínimo `aero.masterads.access_plugin` además del permiso específico declarado en `$requiredPermissions` del controller.
3. THE Master_Ads SHALL otorgar implícitamente al Workspace_Owner todos los permisos del plugin sobre los recursos de su Workspace.
4. THE Master_Ads SHALL otorgar al Workspace_Admin los permisos `manage_meta_accounts`, `access_campaigns`, `run_analysis`, `review_recommendations` y `apply_recommendations` sobre los recursos de su Workspace.
5. THE Master_Ads SHALL otorgar al Workspace_Viewer únicamente `access_plugin` y `access_campaigns` sobre los recursos de su Workspace (sólo lectura).
6. IF un Backend_User invoca una acción AJAX (por ejemplo `onAnalyzeNow`, `onApplyRecommendation`) sin el permiso requerido, THEN THE Master_Ads SHALL devolver HTTP 403 sin ejecutar el job ni mutar estado.

### Requirement 13: Eventos del dominio

**User Story:** Como desarrollador del plugin o integrador externo, quiero que el sistema emita eventos en hitos clave del dominio, para enganchar listeners (notificaciones, analytics, integraciones).

#### Acceptance Criteria

1. WHEN un Meta_Account se conecta exitosamente, THE Master_Ads SHALL despachar `MetaAccountConnected` con el Meta_Account adjunto.
2. WHEN un sync de cuenta termina exitosamente, THE Master_Ads SHALL despachar `SyncCompleted` con el Meta_Account adjunto.
3. WHEN un Ai_Analysis termina con `status = success`, THE Master_Ads SHALL despachar `RecommendationGenerated` con el Ai_Analysis adjunto.
4. WHEN una Recommendation se aplica exitosamente, THE Master_Ads SHALL despachar `RecommendationApplied` con la Recommendation y la Applied_Action adjuntas.
5. THE Master_Ads SHALL registrar el listener `NotifyRecommendationListener` ante `RecommendationGenerated` para notificar a los miembros del Workspace.

### Requirement 14: Rendimiento

**User Story:** Como operador, quiero que el plugin maneje volúmenes razonables de cuentas, campañas y métricas sin degradar el backend ni saturar la Graph_API de Meta.

#### Acceptance Criteria

1. THE Master_Ads SHALL paginar todas las llamadas a la Graph_API mediante un generator (`getPaginated`) que emite elemento a elemento sin acumular toda la respuesta en memoria.
2. WHEN un controller backend recibe una petición que requiere I/O remoto (sync, analysis, apply), THE Master_Ads SHALL responder en menos de 500 ms delegando el trabajo a la queue Redis.
3. THE Meta_Api_Client SHALL aplicar backoff exponencial con esperas de 1, 2, 4, 8 y 16 segundos hasta un máximo de 5 reintentos en respuestas 429 o 613 de Meta.
4. THE Master_Ads SHALL crear los siguientes índices en BD para soportar consultas frecuentes:
   - `insights`: UNIQUE(`entity_type`, `entity_id`, `date`) e index del mismo trío.
   - `recommendations`: index(`ai_analysis_id`, `status`) e index(`status`, `severity`).
   - `usage_records`: index(`subscription_id`, `metric`, `recorded_at`).
   - `applied_actions`: UNIQUE parcial(`recommendation_id`, `success`).
5. WHEN se ejecuta un análisis IA con `lookback_days`, THE Recommendation_Engine SHALL agregar las métricas en una sola pasada (fold) sin queries N+1 sobre Insight.

### Requirement 15: Seguridad y manejo de tokens

**User Story:** Como Workspace_Owner, quiero que los tokens de Meta y las API keys de proveedores IA estén protegidos en reposo y en tránsito, para minimizar el riesgo en caso de fuga del backing store.

#### Acceptance Criteria

1. THE Master_Ads SHALL cifrar mediante `illuminate/encryption` (con `APP_KEY`) los campos `Meta_Account.access_token`, `Meta_Account.refresh_token` y `Ai_Provider.api_key` antes de persistirlos en BD.
2. THE Master_Ads SHALL descifrar transparentemente los campos cifrados mediante mutators/accessors (`getAccessTokenAttribute`, `setAccessTokenAttribute`, equivalentes para `api_key`).
3. THE Master_Ads SHALL nunca incluir `access_token`, `refresh_token` ni `api_key` en respuestas serializadas o logs (declarados en `$hidden`).
4. THE Master_Ads SHALL leer `META_APP_ID`, `META_APP_SECRET`, `META_OAUTH_REDIRECT`, `META_GRAPH_API_VERSION`, `OPENROUTER_API_KEY`, `OPENROUTER_BASE_URL` y `OPENROUTER_DEFAULT_MODEL` exclusivamente vía `config/services.php` o `env()`, sin hardcodearlos en código del plugin.
5. THE Master_Ads SHALL envolver las operaciones de OAuth (`exchangeCode`) y de aplicación de recomendaciones (`apply`) en transacciones DB para garantizar atomicidad (todo-o-nada).
6. IF se detecta un token expirado al iniciar una llamada a Meta, THEN THE Meta_Token_Refresher SHALL intentar refrescarlo automáticamente; si el refresh falla, marcar el Meta_Account con `last_error` y notificar al Workspace_Owner.
7. THE Master_Ads SHALL rechazar formularios backend sin token CSRF válido conforme al estándar de OctoberCMS.

### Requirement 16: Observabilidad

**User Story:** Como operador, quiero logs estructurados, métricas de uso y trazabilidad de errores, para diagnosticar incidentes y entender el costo del servicio.

#### Acceptance Criteria

1. WHEN un job (`SyncMetaAccountJob`, `SyncInsightsJob`, `RunAiAnalysisJob`, `ApplyRecommendationJob`) inicia o termina, THE Master_Ads SHALL emitir logs vía el canal de logging configurado en OctoberCMS incluyendo `correlation_id` por job y `meta_account_id` o `target_id` cuando corresponda.
2. WHEN un Ai_Analysis se completa, THE Master_Ads SHALL persistir `Ai_Analysis.tokens_used` y `Ai_Analysis.cost_usd` calculados por `Ai_Provider::estimateCost(promptTokens, completionTokens)`.
3. THE Master_Ads SHALL exponer en la lista de Ai_Analyses (controller `AiAnalyses`) las columnas filtrables `status`, `tokens_used`, `cost_usd` y `provider.model`.
4. IF un job falla con excepción, THEN THE Master_Ads SHALL persistir el mensaje en `Ai_Analysis.error_message` o `Applied_Action.meta_response.error` según corresponda, además de registrarlo en el log.

### Requirement 17: Compatibilidad RainLab Builder

**User Story:** Como mantenedor del plugin, quiero que el plugin sea editable desde RainLab Builder sin intervención manual, para acelerar la iteración y el onboarding de colaboradores.

#### Acceptance Criteria

1. THE Master_Ads SHALL declarar cada modelo en una carpeta dedicada `models/{NombreModelo}/` y SHALL incluir los archivos `fields.yaml` y `columns.yaml` en cada carpeta.
2. THE Master_Ads SHALL declarar cada controller en una carpeta `controllers/{nombrescontrollers}/` (lowercase plural) e incluir los archivos `config_form.yaml` y `config_list.yaml`.
3. THE Master_Ads SHALL ubicar todas las migraciones planas dentro de `updates/`, una por tabla, registradas en `version.yaml`, sin subcarpetas ni seeders.
4. THE Master_Ads SHALL nombrar las migraciones siguiendo el patrón `<verbo>_<tabla>_table.php` (por ejemplo `create_workspaces_table.php`).
5. THE Master_Ads SHALL declarar en `version.yaml` las 13 migraciones del plugin en el orden definido en el design (workspaces → workspace_user → plans → subscriptions → usage_records → ai_providers → meta_accounts → campaigns → ad_sets → ads → insights → ai_analyses → recommendations → applied_actions).
6. THE Master_Ads SHALL utilizar exclusivamente traits/behaviors del core de Rain (`Validation`, `SoftDelete`, `Sluggable`, `Sortable`) en los modelos expuestos a Builder, evitando traits custom.
7. THE Master_Ads SHALL registrar permisos con prefijo `aero.masterads.*` en `registerPermissions()` para que aparezcan en la UI de roles de Builder.
8. THE Master_Ads SHALL implementar los controllers usando exclusivamente `FormController`, `ListController`, `RelationController`, `ImportExportController` y `ReorderController` del core del módulo Backend.

### Requirement 18: Internacionalización

**User Story:** Como Workspace_Member hispanohablante, quiero la interfaz backend del plugin en español, para usar el sistema con la menor fricción posible.

#### Acceptance Criteria

1. THE Master_Ads SHALL proveer el archivo `lang/es/lang.php` con todas las cadenas visibles del backend del plugin.
2. THE Master_Ads SHALL referenciar las cadenas mediante el prefijo `aero.masterads::lang.<key>` en `fields.yaml`, `columns.yaml`, `config_form.yaml`, `config_list.yaml` y la definición de permisos.
3. WHERE OctoberCMS está configurado con `locale = es`, THE Master_Ads SHALL renderizar el backend del plugin en español.

### Requirement 19: Escalabilidad

**User Story:** Como operador, quiero que el plugin escale horizontalmente y soporte múltiples workers, para incrementar throughput de sync y análisis sin colisiones.

#### Acceptance Criteria

1. THE Master_Ads SHALL ejecutar todos los jobs de sync, análisis y aplicación exclusivamente en la queue Redis (no inline en el request HTTP).
2. WHILE corre un comando programado del plugin (`masterads:sync-all`, `masterads:rotate-tokens`, `masterads:analyze`), THE Master_Ads SHALL aplicar `withoutOverlapping()` para evitar ejecuciones concurrentes del mismo schedule.
3. WHILE el plugin se despliega en múltiples nodos, THE Master_Ads SHALL aplicar `onOneServer()` en `masterads:sync-all` para que sólo un servidor lo ejecute.
4. THE Master_Ads SHALL hacer cada operación de upsert (Campaign, Ad_Set, Ad, Insight) idempotente respecto a su clave natural (`meta_id` o tupla única), permitiendo reintentos seguros del job sin duplicar datos.
5. THE Master_Ads SHALL hacer la operación de aplicación de Recommendation idempotente respecto a `recommendation_id`, garantizando una única llamada exitosa a Meta sin importar cuántas veces se invoque `apply`.

### Requirement 20: Restricciones del MVP backend-only

**User Story:** Como Product Owner, quiero limitar el alcance de esta primera versión a backend de OctoberCMS, para entregar valor rápidamente y diferir el frontend público a una iteración posterior.

#### Acceptance Criteria

1. THE Master_Ads SHALL exponer toda funcionalidad para Backend_User vía controllers que extienden `Backend\Classes\Controller` con `FormController`, `ListController` y `RelationController`.
2. THE Master_Ads SHALL NOT entregar componentes CMS (frontend) en esta versión MVP; la única ruta frontend permitida es el callback OAuth declarado en el Requirement 11.
3. THE Master_Ads SHALL usar el namespace `Aero\MasterAds` y la estructura `plugins/aero/masterads/` siguiendo las convenciones de OctoberCMS 4.
4. THE Master_Ads SHALL ser compatible con `october/rain ^4.2`, `guzzlehttp/guzzle ^7.8` e `illuminate/encryption ^12.0` declarados en `composer.json`.
5. THE Master_Ads SHALL aceptar como dependencia opcional `league/oauth2-client ^2.7` o, alternativamente, usar Guzzle directo para el flujo OAuth.
