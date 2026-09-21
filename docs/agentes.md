# Agentes automáticos

Dos agentes trabajan solos por cron en este servidor. Ambos están pensados para convivir con
varias sesiones de trabajo a la vez y con edición manual. **Ninguno hace push ni toca producción.**

| Agente | Qué hace | Cron | Estado |
|---|---|---|---|
| `git-agent` | Commitea lo pendiente, un commit por área | cada 10 min | activo |
| `docs-sync` (vigilante + agente IA) | Mantiene al día la documentación del tenant en Aero.Docs | cada 30 min | activo (`dry_run: false`) |

Ver crons: `crontab -l` (marcas `# git-agent (auto)` y `# docs-sync-watch (auto)`).

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
- **Coordinación:** el área `plugins/aero/docs` queda en «espera» mientras `.opencode/.docs-sync.lock` esté vivo (ver §2).

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

## 2. docs-sync (documentación del tenant)

Ubicación: `.opencode/` (`docs-sync-watch.py`, `docs-sync.json`, `agent/docs-sync.md`, `skills/docs-plugin/SKILL.md`, `commands/docs-sync.md`).

Tiene dos piezas:

1. **Vigilante determinista** (`docs-sync-watch.py`, cron cada 30 min). Sin IA. Compara la
   `plugin_version` documentada en `aero_docs_articles` con la última versión de
   `plugins/aero/<plugin>/updates/version.yaml`. Solo si hay un plugin desactualizado invoca al agente.
2. **Agente IA `docs-sync`** (`opencode run --agent docs-sync`). Recibe un brief con la versión actual,
   la documentada y las notas de versión posteriores. Documenta **solo el lado tenant**: un artículo `.md`
   por formulario/función, seeder `seed_<plugin>_docs.php`, actualiza `funcionalidades.md`. Si la versión no
   cambia nada visible para el tenant, no toca artículos (resultado válido).

**Permisos mínimos del agente** (frontmatter de `agent/docs-sync.md`): escribe únicamente en
`plugins/aero/docs/`; bash con lista blanca (git de lectura, grep, cat, ls…, artisan tinker/cache:clear
como `www`); denegados `git add/commit/push/reset…`, `ssh`, `scp`, `rsync`, redirecciones y `webfetch`.

**Solo local.** Producción no se toca. El código y los seeders viajan con el flujo normal:
`git push` → en producción `git pull` + `git submodule update` + `october:migrate` (con respaldo de BD).

**Guardas (el vigilante aplaza si…):**
- `plugins/aero/docs/` tiene cambios sin commitear o editados hace < 15 min (tu trabajo manual).
- El plugin fuente se editó hace < 30 min.
- Hay otro docs-sync corriendo (lock `.opencode/.docs-sync.lock`; si su pid murió, se retira).
- Se alcanzó el tope de 4 corridas al día, o un plugin falló (reintenta a los 180 min; tras 3 fallos deja de intentar hasta `reset`).
- Está pausado. Se pausa **solo** si el agente modificó rutas fuera de `plugins/aero/docs/` o `.opencode/`; no revierte nada, avisa en el log.

**Comandos.**
```bash
.opencode/docs-sync-watch.py status               # desactualizados, en espera, sin documentar
.opencode/docs-sync-watch.py run [--dry-run]      # una corrida (dry-run simula)
.opencode/docs-sync-watch.py bootstrap <plugins>  # documentar por primera vez (manual)
.opencode/docs-sync-watch.py pause | resume       # frenar / reanudar
.opencode/docs-sync-watch.py reset <plugin>       # olvidar fallos y "revisado" de un plugin
.opencode/docs-sync-watch.py install-cron | uninstall-cron
```
En opencode: `/docs-sync aero/crm` documenta un plugin a mano.

**Plugins sin documentar** (`aifields, api, chat, connector, credits, masterads, notify, services, tracking, wapi`):
el vigilante no los toma solo; se hace con `bootstrap` de 2 o 3 a la vez y se revisa en `/documentacion`.

**Config** (`.opencode/docs-sync.json`): `dry_run` (true = simula), `docs_quiet_minutes` 15, `source_quiet_minutes` 30,
`max_plugins_per_run` 3, `max_runs_per_day` 4, `retry_backoff_minutes` 180, `max_failures` 3, `agent_timeout_minutes` 45.

**Hook post-commit (opcional).** `bin/docs-sync-install.sh --with-hook` instala un hook que solo avisa al
vigilante (mismas guardas). Sin él no se pierde nada. `bin/docs-sync-install.sh --remove` quita cron y hook.

Log: `.opencode/docs-sync.log`. Estado: `.git/docs-sync/`.

---

## Cómo conviven

```
tú editas docs ──► vigilante espera (15 min) ──► git-agent commitea (8 min de calma)
plugin sube de versión ──► vigilante (30 min) ──► agente escribe docs (lock activo)
                                          └──► git-agent espera el área docs hasta soltar el lock
```

## Problemas frecuentes

| Síntoma | Causa / solución |
|---|---|
| Vigilante «PAUSADO» | El agente escribió fuera de docs. Revisa `git status`, corrige y `docs-sync-watch.py resume`. |
| Docs no se actualizan | `status`: puede estar en espera por edición reciente, tope diario o backoff. |
| `NOAUTH` en artisan | Se usó `REDIS_PASSWORD=` vacío o artisan como root. Siempre `sudo -u www /www/server/php/84/bin/php artisan …`. |
| Área sin commitear | `git-agent status`: bloqueada por validación, o esperando el lock de docs-sync. |
