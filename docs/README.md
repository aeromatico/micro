# micro.clouds.com.bo — Documentación del Proyecto

> Última actualización: 2026-06-10 | Repo: https://github.com/aeromatico/micro

## Índice

- [Entorno de Producción](entorno.md)
- [Stack Tecnológico](stack.md)
- [Estructura del Proyecto](estructura.md)
- [Skills de Desarrollo (Claude Code)](skills/README.md)
  - [Skills de Frontend](skills/frontend.md)
  - [Skills de Backend](skills/backend.md)
- [Flujos de Trabajo](workflows.md)
- [Convenciones](convenciones.md)

## Git

Flujo Antigravity: este servidor es la fuente, nunca hace pull.

```bash
# Commit automático con git-agent
.claude/agents/git-agent/git-agent.sh

# Dry-run (ver qué commitearía)
.claude/agents/git-agent/git-agent.sh --dry-run --verbose
```

> **Nota:** `.claude/` está en `.gitignore` — los skills son herramientas locales de desarrollo.
