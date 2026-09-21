# git-agent v2 — commits automáticos y seguros para micro.clouds.com.bo

Detecta lo que cambió, lo agrupa **por área** (plugin, tema, submódulo…) y hace un commit
Conventional Commits por área, con rutas explícitas. Corre por cron cada 10 minutos.

> Nació de un problema real (2026-09-21): trabajando en muchas áreas a la vez, los commits
> se acumulaban (51 sin subir), había 209 entradas rotas en el índice de git y varias sesiones
> se pisaban. La v1 (junio) era manual, hacía un solo commit para todo y empujaba con
> `git pull --rebase`; nunca estuvo programada.

## Reglas de seguridad (por qué es seguro con varias sesiones)

| Regla | Efecto |
|---|---|
| **Commit por área con rutas** (`git commit -o -- <rutas>`) | Nunca usa `git add -A`; lo que otra sesión dejó preparado sigue preparado y sin commitear. |
| **Espera a que se "enfríe"** (`quiet_minutes`, 8) | No commitea archivos que se editaron hace poco. Tope: si un área lleva `max_wait_minutes` (90) pendiente, se commitea con solo 2 min de calma. |
| **Valida antes de commitear** | Sintaxis PHP/JSON/version.yaml, que `version.yaml` no referencie migraciones inexistentes, secretos y archivos >2 MB. Un área con problemas **no se commitea** y se reporta. |
| **Archivos prohibidos** | `.env`, `*.sql`, `*.sql.gz`, `*.zip`, `*.log`, `storage/`, `vendor/`, `node_modules/`, tokens ACME… se omiten y se avisa. |
| **No commitea en estados raros** | `index.lock`, merge/rebase en curso, rama distinta de `main`, otro `git` corriendo, otra instancia del agente. |
| **Autorreparación del índice** | Si el índice referencia objetos inexistentes hace `git read-tree HEAD` (con copia y sin tocar el árbol de trabajo). |
| **Sin hooks** | Commitea con `core.hooksPath=/dev/null`: no dispara el hook `post-commit` de docs-sync ni otros. |
| **Nunca pull/rebase/reset/force** | Este servidor es la fuente de verdad. |
| **Submódulos** | Commitea *dentro* de hello/pay/aifields/api y luego un commit de punteros en el padre. |

Los mensajes salen de las notas de `updates/version.yaml` del plugin (`feat(docs): Docs multi-sitio…`,
`fix(...)` si la nota empieza con «Corrige…»); sin notas, un mensaje genérico con la lista de archivos.
Todos llevan el trailer `Auto-Commit: git-agent v2`.

## Comandos

```bash
git-agent status              # qué hay pendiente por área y su estado (listo / espera / bloqueado)
git-agent status --now        # igual, ignorando la espera
git-agent commit --dry-run    # simula qué commitearía
git-agent commit [--now] [--area docs]   # commitea (una corrida)
git-agent run --quiet         # lo que ejecuta el cron: commit (+ push si push.auto=true)
git-agent push [--dry-run]    # push con compuertas (submódulos primero, padre al final)
git-agent doctor [--fix]      # salud: índice, locks, hooks, sesiones, cron
git-agent install-cron | uninstall-cron
```

`git-agent` es un symlink a `git-agent.sh`, que llama a `git-agent.py`.

## Push (separado y con compuertas)

`git-agent push` solo empuja si **todas** se cumplen: sin cambios sin commitear (padre y
submódulos), el remoto no divergió (avance rápido), sin secretos en lo que se subiría, las URLs de
salud responden (`panel.market.com.bo/backend`, `market.com.bo/`), las versiones de plugins
coinciden con la BD y `credits:verify` está limpio. Sube los submódulos primero.
Por defecto el cron **no** empuja (`push.auto=false`); para activarlo, en `config.json`:
`{"push": {"auto": true}}`.

## Configuración (`config.json`, se fusiona con los valores por defecto)

```json
{ "quiet_minutes": 8, "max_wait_minutes": 90, "max_file_mb": 2,
  "push": { "auto": false }, "cron": { "commit_every_minutes": 10 } }
```

## Dónde mirar

- `git-agent status` y `git-agent doctor`
- Log: `.git/git-agent/agent.log` (dentro de `.git`: no se versiona ni ensucia `storage/`)
- Última corrida: `.git/git-agent/status.json`

## Si algo se bloquea

`git-agent status` dice el motivo por área (error de sintaxis con archivo y línea, migración que
falta, posible secreto…). Se corrige y la próxima corrida lo commitea sola. Para forzar ahora:
`git-agent commit --now --area <texto>`.
