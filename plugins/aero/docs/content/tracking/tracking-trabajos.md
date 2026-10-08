# Formulario: Trabajos

**Ruta:** `Tracking → Trabajos`
**Controlador:** `Aero\Tracking\Controllers\Jobs`
**Modelo:** `Aero\Tracking\Models\Job`
**Permiso:** `aero.tracking.use`

Un **trabajo** es un recorrido: una unidad de trabajo que se mueve entre puntos.
No sabe qué representa (pedido, carga, traslado); eso lo describes en el título y
la referencia.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Título** | — | Ej. «Pedido #1042», «Traslado a Santa Cruz». |
| **Referencia** | — | Código externo con el que lo identificas. |
| **Estado** | requerido | Ver tabla de estados abajo. |
| **Activo asignado** | — | Vehículo/persona que lo ejecuta (solo activos activos). |
| **Programado para** | — | Fecha y hora previstas. |
| **Notas** | — | Detalle libre. |

## Paradas

Las paradas son los puntos del recorrido. Agrega una por fila:

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Tipo** | — | Recojo, Entrega o Servicio. |
| **Nombre** | — | Nombre del punto. |
| **Dirección** | — | Dirección textual. |
| **Latitud** / **Longitud** | — | Coordenadas del punto en el mapa. |
| **Contacto** / **Teléfono** | — | Persona a contactar en la parada. |
| **Notas** | — | Indicaciones para esa parada. |

## Estados

| Estado | Significado |
|--------|-------------|
| `pending` | Pendiente. |
| `assigned` | Asignado (ya tiene activo). |
| `in_progress` | En curso. |
| `completed` | Completado. |
| `cancelled` | Cancelado. |
| `failed` | Fallido. |

Los estados **Pendiente**, **Asignado** y **En curso** cuentan como **trabajos
abiertos**: aparecen junto al activo en el mapa en vivo.

> [!TIP]
> Cargá la latitud y longitud de las paradas: el mapa usa esas coordenadas para
> ubicarlas.

> [!NOTE]
> Al guardar el trabajo se guardan todas sus paradas como una lista nueva
> (las anteriores se reemplazan).
