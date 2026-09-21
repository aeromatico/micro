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
- [Plan: llamadas WhatsApp con IA](voice-agent-plan.md)
- [Convenciones](convenciones.md)
- [Agentes automáticos (git-agent y docs-sync)](agentes.md)

## Git

Flujo Antigravity: este servidor es la fuente, nunca hace pull.

Los commits los hace `git-agent` por cron (cada 10 min) y la documentación del tenant la mantiene
`docs-sync` (cada 30 min). Push manual: `git-agent push`. Detalle en [Agentes automáticos](agentes.md).

```bash
git-agent status          # qué hay pendiente por área
git-agent commit --now    # commitear ahora
```

> **Nota:** `.claude/` está en `.gitignore` — los skills son herramientas locales de desarrollo.
