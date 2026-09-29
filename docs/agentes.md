# Agentes automáticos

Dos agentes trabajan solos por cron en este servidor. Ambos están pensados para convivir con
varias sesiones de trabajo a la vez y con edición manual. **Ninguno hace push ni toca producción.**

| Agente | Qué hace | Cron | Estado |
|---|---|---|---|
| `git-agent` | Commitea lo pendiente, un commit por área | cada 10 min | activo |
| `docs-sync` (detección + `docs-cli`) | Detecta qué documentar; Claude genera solo lo que eliges, siempre como borrador | cada 30 min (solo detecta) | activo |

Ver crons: `crontab -l` (marcas `# git-agent (auto)` y `# docs-sync-claude (auto)`).

---

## 1. git-agent (commits automáticos)

Ubicación: `.claude/agents/git-agent/` (`git-agent.py`, `config.json`, `README.md`). Comando global: `git-agent`.

**Qué hace.** Agrupa los cambios por área (plugin, tema, submódulo, `bin`, `.opencode`…) y hace un
commit Conventional Commits por área con rutas explícitas (`git commit -o -- <rutas>`), nunca `git add -A`.
Los mensajes salen de las notas de `updates/version.yaml` del plugin. Trailer: `Auto-Commit: git-agent v2`.

**Cuándo commitea.**
- Espera a que los archivos "se enfríen" (`quiet_minutes`: 8). Tope: un área con 90 min pendiente se commitea con 2 min de calma.
- Valida antes: sintaxis PHP/JSON/`version.yaml`, migraciones referenciadas que existan, secretos, archivos > 2 MB. Un área con problemas **no se commitea** y se reporta.
- Omite `.env`, `*.sql`, `*.zip`, `*.log`, `storage/`, `vendor/`, `node_modules/`.
- No actúa con `index.lock`, merge/rebase en curso, rama distinta de `main` ni otra instancia corriendo.
- Si el índice tiene objetos rotos, lo repara con `git read-tree HEAD` (guarda copia).
- Submódulos (hello, pay, aifields, api): commitea dentro y luego un commit de punteros en el padre.
- **Coordinación:** el área `plugins/aero/docs` queda en «espera» mientras `.git/docs-sync-claude/agent.lock` esté vivo (ver §2).

**Push.** Automático desactivado (`push.auto: false`). Manual y con compuertas:
`git-agent push [--dry-run]` (sin cambios pendientes, sin divergencia, sin secretos, URLs de salud OK,
versiones de plugins = BD, `credits:verify` limpio; submódulos primero).

**Comandos.**
```bash
git-agent status [--now]                 # pendiente por área: listo / espera / bloqueado
git-agent commit [--now] [--area texto]  # commitea una vez (--dry-run simula)
git-agent push [--dry-run]               # sube con compuertas
git-agent doctor [--fix]                 # salud: índice, locks, hooks, cron
git-agent install-cron | uninstall-cron
```
Log: `.git/git-agent/agent.log`. Estado: `.git/git-agent/status.json`.

**Config** (`config.json`, se fusiona con los valores por defecto): `quiet_minutes`, `max_wait_minutes`,
`max_file_mb`, `push.auto`, `cron.commit_every_minutes`, `busy_locks`.

**Si un área se bloquea:** `git-agent status` dice el motivo (sintaxis con archivo y línea, migración que falta, posible secreto). Se corrige y la siguiente corrida commitea sola; para forzar: `git-agent commit --now --area <texto>`.

---

## 2. docs-sync (documentación del tenant y guías interactivas)

**Nada se genera solo.** El cron (cada 30 min) solo *detecta*: `docs-cli scan` recalcula el backlog sin IA y
actualiza el contador del menú Docs del backend. Tú eliges qué incorporar con **`docs-cli`** y solo entonces
se llama a Claude (Sonnet), siempre como borrador.

```bash
docs-cli                         # interactivo: lista numerada, elegir (1,3,5-7 · all), omitir (s), anotar (n), filtrar (f)
docs-cli list [--plugin P] [--all] [--json]
docs-cli generate guide:guia-api-apikeys doc:credits --note "enfócate en los permisos" [--yes]
docs-cli skip guide:guia-docs-guides   # no necesita guía (persistente, en git)
docs-cli unskip guide:guia-docs-guides
```
Ids: `guide:<slug>` (guía de un formulario), `doc:<plugin>` (actualizar documentación), `bootstrap:<plugin>`
(documentar por primera vez). Las **notas** (`n <nº> texto` o `--note`) se pasan a Claude como instrucción
del usuario para ese elemento. Coste estimado por elemento: guía ~$0.35–0.50, doc ~$0.60, doc nueva ~$2.
Omitidos: `plugins/aero/docs/content/guides/exclude.json` (formularios) y `content/docs-skip.json`
(documentación, reaparece sola en la siguiente versión del plugin).

El backlog sale de `artisan docs:backlog` (PHP: `Aero\Docs\Classes\Backlog`), que reutiliza el hash de fuentes
de cada formulario (`GuideSources`).

Motor: **Claude** (`claude -p`, modelo Sonnet). Sustituyó a opencode, que dejó de funcionar el 2026-09-21.
Ubicación: `.claude/agents/docs-sync/` (`docs-sync-watch.py`, `docs-sync.json`, `agent.md`, `settings.json`) y skills
`.claude/skills/docs-plugin/` y `.claude/skills/form-guide/` (esta trae `reference.html`, la guía modelo).

Tiene dos piezas:

1. **Detección determinista** (`docs:backlog`; `docs-sync-watch.py` conserva el motor de ejecución y el modo totalmente automático, hoy con `dry_run: true`). Sin IA. Decide dos cosas:
   - *Documentación:* la versión documentada (`docs:versions`, incluye ediciones pendientes de aprobar) es menor
     que la última de `plugins/aero/<plugin>/updates/version.yaml`.
   - *Guías:* un formulario principal sin guía o cuyo `source_hash` cambió (`docs:guides`). El hash cubre
     `config_form.yaml`, `fields.yaml`, las opciones `get*Options()` del modelo, `config_relation.yaml` y los partials.
     Detecta cambios de formulario aunque no suba la versión. Solo se generan guías de plugins ya documentados.
2. **Agente Claude** (`claude -p` con `agent.md` y `settings.json` propios). Recibe un brief con lo que toca,
   escribe `.md` y `.html` bajo `plugins/aero/docs/content/` y ejecuta `docs:import`.

**Todo es borrador.** `docs:import` nunca pisa lo publicado:
- artículo nuevo → sin publicar; artículo publicado con cambios → «cambios pendientes» (pestaña en Docs → Artículos);
- guía nueva → borrador; guía publicada regenerada → propuesta pendiente (Docs → Guías interactivas, con vista previa).
Se aprueba con el permiso `aero.docs.guides.review`. El público las ve en `/guias` y desde el artículo de docs enlazado.

**Permisos del agente** (`settings.json`, `--permission-mode dontAsk`, `--setting-sources project`): escribe
únicamente en `plugins/aero/docs/content/`; Bash limitado a git de lectura, `ls`, `wc` y `artisan docs:*` como `www`;
denegados `git add/commit/push…`, `ssh`, `rm`, web. No hereda `.claude/settings.local.json`.

**Guardas (el vigilante aplaza si…):**
- `plugins/aero/docs/` tiene cambios sin commitear o editados hace < 15 min.
- El plugin fuente se editó hace < 30 min, o hay otro docs-sync corriendo (lock `.git/docs-sync-claude/agent.lock`).
- Tope de 4 corridas al día, o un plugin falló (reintenta a los 180 min; tras 3 fallos, `reset`).
- Máximo 4 guías por corrida (`max_guides_per_run`) y `max_budget_usd` por corrida.
- Está pausado: por escritura fuera de `plugins/aero/docs/`, o por **autenticación de Claude caducada**
  (no gasta reintentos; ejecuta `claude`, `/login` y luego `resume`).

**Comandos.**
```bash
.claude/agents/docs-sync/docs-sync-watch.py status               # qué toca, en espera, sin documentar
.claude/agents/docs-sync/docs-sync-watch.py run [--dry-run]      # una corrida (dry-run simula)
.claude/agents/docs-sync/docs-sync-watch.py bootstrap <plugins>  # documentar por primera vez (manual)
.claude/agents/docs-sync/docs-sync-watch.py pause | resume | reset <plugin>
.claude/agents/docs-sync/docs-sync-watch.py install-cron | uninstall-cron
sudo -u www /www/server/php/84/bin/php artisan docs:guides [plugin]   # plan de guías (JSON)
sudo -u www /www/server/php/84/bin/php artisan docs:import <plugin>   # importar como borrador
```
Interactivo: `/form-guide` genera o pule la guía de un formulario concreto (p. ej. con Claude Design) y
`docs:import` la deja como propuesta.

**Excluir formularios de las guías:** añade `"plugin/controlador"` a `plugins/aero/docs/content/guides/exclude.json`.

**Solo local.** Las aprobaciones viven en la BD de cada entorno. En otro entorno, tras `git pull`, se
ejecuta `docs:import` y se aprueba allí.

**Config** (`.claude/agents/docs-sync/docs-sync.json`): `dry_run`, `model`, `max_turns`, `max_budget_usd`,
`docs_quiet_minutes`, `source_quiet_minutes`, `max_plugins_per_run`, `max_guides_per_run`, `max_runs_per_day`,
`retry_backoff_minutes`, `max_failures`, `agent_timeout_minutes`, `guides_enabled`.

Log y estado: `.git/docs-sync-claude/`. El vigilante de opencode (`.opencode/`) queda **pausado** y se retira al activar este.

---

## Cómo conviven

```
tú editas docs ──► vigilante espera (15 min) ──► git-agent commitea (8 min de calma)
plugin cambia (versión o formulario) ──► vigilante (30 min) ──► Claude escribe borradores (lock activo)
                                          └──► git-agent espera el área docs hasta soltar el lock
```

## Problemas frecuentes

| Síntoma | Causa / solución |
|---|---|
| Vigilante «PAUSADO» | El agente escribió fuera de docs, o Claude perdió la sesión. Mira `status`, corrige (o `claude` → `/login`) y `resume`. |
| Docs no se actualizan | `status`: puede estar en espera por edición reciente, tope diario o backoff. |
| `NOAUTH` en artisan | Se usó `REDIS_PASSWORD=` vacío o artisan como root. Siempre `sudo -u www /www/server/php/84/bin/php artisan …`. |
| Área sin commitear | `git-agent status`: bloqueada por validación, o esperando el lock de docs-sync. |
