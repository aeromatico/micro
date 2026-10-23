---
title: Seguimiento externo
sort: 40
---
# Formulario: Seguimiento externo

**Ruta:** `Tracking → Seguimiento externo`
**Controlador:** `Aero\Tracking\Controllers\Feeds`
**Modelo:** `Aero\Tracking\Models\Feed`
**Permiso:** `aero.tracking.use`

Sigue un pedido cuyo rastreo está en el **enlace público de un tercero** (hoy, PedidosYa): pegas el enlace «compartir pedido» y Market registra su estado, la hora estimada, los mensajes y la posición del repartidor.

## Iniciar un seguimiento

En la lista pulsa **Seguir enlace** y completa:

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Enlace público de rastreo** | requerido | El enlace de «compartir pedido» del proveedor. Hoy se admite PedidosYa. |
| **Nombre (opcional)** | máx. 191 | Cómo quieres ver este seguimiento en la lista. Si lo dejas vacío se usa el título que informa el proveedor. |

Si el enlace no es de un proveedor admitido, el sistema rechaza el alta con un mensaje de error. Al guardar se crea además un [trabajo](tracking-trabajos) vinculado al seguimiento.

## Pantalla de seguimiento (al editar)

Todos estos datos son de solo lectura y se actualizan solos:

| Campo | Qué muestra |
|-------|-------------|
| **Proveedor** | De dónde viene el enlace. |
| **Estado del seguimiento** | Siguiendo, Entregado, Cancelado, Enlace vencido, Falló o Detenido. |
| **Fase del pedido** | En cola, Confirmado, Repartidor en el comercio, En camino, Entregado o Cancelado. |
| **Hora estimada** | ETA informada por el proveedor. |
| **Mensaje del proveedor** | Último mensaje recibido. |

Además verás enlaces al **trabajo** asociado y a la **última posición** del repartidor en el mapa, la hora de la última lectura, el último error si lo hubo y la **línea de tiempo** de eventos.

## Acciones

- **Actualizar ahora**: consulta al proveedor en ese momento (si no, se actualiza periódicamente).
- **Detener seguimiento**: deja de consultar el enlace; el estado pasa a *Detenido*.

> [!NOTE]
> El seguimiento termina solo cuando el pedido se entrega o cancela, o cuando el enlace del proveedor vence.

Relacionado: [Trabajos](tracking-trabajos) · [Mapa en vivo](tracking-mapa-en-vivo) · [Vista general](tracking-vista-general)

**Versión documentada:** 1.2.0
