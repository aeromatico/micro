---
title: Opiniones de los clientes
sort: 85
---
# Opiniones de los clientes

**Ruta:** `Tienda → Opiniones`
**Controlador:** `Aero\Shop\Controllers\Feedbacks`
**Modelo:** `Aero\Shop\Models\Feedback`
**Permiso:** `aero.shop.manage_orders`

Cuando un cliente termina su pedido puede dejar de 1 a 5 estrellas y un comentario. Aquí las ves y decides cuáles se muestran en tu tienda.

## Activarlas

En [Configuración de tienda](shop-configuracion), campo **Opiniones de los clientes**. La política decide qué pasa con cada opinión nueva:

| Política | Qué pasa |
|----------|----------|
| **No pedir opiniones** | Valor por defecto. No se pide nada al cliente. |
| **Solo para el negocio (análisis interno)** | Se pide la opinión y queda **Interna**: solo la ves tú. |
| **Públicas: se muestran en la tienda al instante** | Queda **Pública** y aparece en `/tienda` de inmediato. |
| **Públicas con tu aprobación** | Queda **Pendiente de aprobación** hasta que tú la publiques. |

El cambio de política solo afecta a las opiniones nuevas; las que ya existen conservan su estado.

## Qué ve el cliente

En la página de su pedido, el cliente ve «¿Qué tal estuvo tu pedido?» con cinco estrellas, un comentario (hasta 1000 caracteres) y su nombre (hasta 60), ambos opcionales. Solo elegir las estrellas es obligatorio. El mensaje le avisa si su opinión será solo para el local, se publicará al instante o cuando el local la apruebe.

- Se pide cuando el pedido ya está **listo o entregado** (en restaurantes, según la cocina) o **pagado / despachado** (en los demás). No se pide en pedidos cancelados o reembolsados.
- Hay **una opinión por pedido**: después de enviarla, el cliente ve «¡Gracias por tu opinión!» y no puede enviar otra.

## Lista

| Columna | Qué muestra |
|---------|-------------|
| **Fecha** | Cuándo se envió. |
| **Pedido** | Número del pedido. |
| **Estrellas** | Calificación. |
| **Comentario** | Texto (resumido). |
| **Cliente** | Nombre que escribió, si lo puso. |
| **Estado** | Interna, Pendiente de aprobación, Pública u Oculta. |

Puedes filtrar por **Estado**. Las más recientes salen primero. Si aún no hay opiniones, la lista te recuerda activarlas en la configuración.

## Moderar una opinión

Al abrir una opinión ves las estrellas, el nombre y el comentario (solo lectura) y cambias el **Estado**:

| Estado | Significado |
|--------|-------------|
| **Interna (solo para el negocio)** | Solo la ves tú. |
| **Pendiente de aprobación** | Espera tu decisión. |
| **Pública (se muestra en la tienda)** | Se muestra en la tienda. |
| **Oculta** | La retiras de la tienda; solo la ves tú. |

## Dónde se muestran

Las opiniones **Públicas** aparecen en la tienda (`/tienda`) con el promedio de estrellas, solo si la política es *Públicas* o *Públicas con tu aprobación*. Si cambias la política a *No pedir* o *Solo para el negocio*, deja de mostrarse el bloque aunque haya opiniones públicas guardadas.

> [!TIP]
> Con *Públicas con tu aprobación* puedes publicar las buenas y dejar **Ocultas** las que no quieras mostrar, sin perderlas.

> [!NOTE]
> El cliente ve el estado de su opinión: «Se publicará en la tienda cuando el local la revise» (pendiente), «Tu opinión ya se muestra en la tienda» (pública) o «Le ayuda al local a mejorar» (interna u oculta).

**Versión documentada:** 1.13.1
