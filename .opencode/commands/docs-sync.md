---
description: Sincroniza la documentación del tenant de los plugins tocados por un commit (o los indicados)
agent: docs-sync
---

Sincroniza la documentación del tenant siguiendo el agente `docs-sync`.

Entrada: `$ARGUMENTS`

- Si es un commit o rango (`HEAD`, `abc123`, `A..B`), analiza ese rango.
- Si es una lista de plugins (`aero/notify aero/shop`), documenta/actualiza esos.
- Si está vacío, usa el **último commit** (`HEAD`) y deduce los plugins del diff.

Recuerda: **solo funciones del panel tenant**, nunca superadmin. Publica en local
y producción y verifica los links. Al final, reporta artículos creados/actualizados
y la versión documentada.
