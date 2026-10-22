---
title: MCP — vista general
sort: 1
featured: false
---
# Servidor MCP

**Ruta:** menú **API & MCP → Keys MCP** (`/backend/aero/mcp/mcpkeys`) · **Permiso:** `aero.mcp.manage_keys`

MCP permite que un cliente externo (por ejemplo, un asistente de IA) use funciones de tu negocio con una **key**. El endpoint es `/api/v1/mcp`.

## Keys MCP

Las keys MCP se emiten desde una pantalla propia dentro del menú «API & MCP». Cada key:

- Solo puede tener **módulos MCP** (lectura o escritura).
- Pertenece a un **tenant** (obligatorio).
- Tiene una **expiración** obligatoria.

## Qué puede hacer una key

| Permiso (scope) | Qué da |
| --- | --- |
| `mcp.use` | Usar el endpoint MCP. |
| `mcp.module.<módulo>` | Todas las herramientas de **lectura** del módulo. |
| `mcp.module.<módulo>.write` | Las herramientas que **modifican** datos del módulo. |
| `mcp.tool.<nombre>` | Una herramienta concreta. |

Ninguna key recibe permisos por defecto: concede lo mínimo necesario.

> [!IMPORTANT]
> Solo ves y puedes usar los módulos que la plataforma haya activado y que incluya el plan de tu negocio. Las herramientas siempre trabajan sobre los datos de tu negocio.

> [!WARNING]
> Una key con permiso de escritura puede modificar datos. Dale expiración corta y revócala si dejas de usarla.

**Versión documentada:** 1.3.0
