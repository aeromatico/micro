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

## Restaurantes: estar atento al local

Con los módulos **shop** y **pos** activados, la key MCP de un restaurante recibe estas tools de **solo lectura**:

| Tool | Para qué sirve |
|------|----------------|
| `restaurant_overview` | Resumen rápido ahora: si está abierto, la cocina (nuevos, preparando, listos, retrasados), pedidos y ventas de hoy, emergencias activas y lo que requiere atención. |
| `restaurant_alerts` | Lo urgente, ordenado: **emergencias de mesa** (con el número de mesa), pedidos nuevos sin aceptar, retrasados, listos sin entregar y cuentas por cobrar. Con `since` devuelve solo lo nuevo desde tu última consulta. |
| `kitchen_board` | El tablero de cocina por columnas, con mesa, platos, extras, notas, minutos de espera y si va retrasado. |
| `restaurant_orders` | Busca pedidos por **número del día** («18»), número de pedido, mesa, cliente, estado, tipo, cobrado o no, y fecha. |
| `pos_overview` | La caja ahora: turnos abiertos y efectivo esperado, ventas de hoy por método de pago, pedidos por cobrar y emergencias. |
| `pos_sales_recent` | Ventas recientes de la caja con pagos, NIT y cajero. |
| `pos_pending_orders` | Pedidos que los clientes hicieron desde su mesa y esperan cobrarse en caja. |

**Cómo «estar atento»:** el MCP responde cuando se le consulta; no envía avisos por su cuenta. Por eso el servidor le indica al asistente que consulte `restaurant_alerts` al empezar y cada vez que pregunten qué pasa, y que repita con `since` si debe vigilar. La emergencia de una mesa siempre se dice primero.

> [!NOTE]
> Estas tools solo leen: no aceptan pedidos, no cobran ni marcan emergencias como atendidas. Esas acciones se hacen desde la cocina o la caja.

