# Función: Pipeline

**Ruta:** `CRM → Pipeline`
**Controlador:** `Aero\Crm\Controllers\Deals`
**Modelo:** `Aero\Crm\Models\Deal`
**Permiso:** `aero.crm.manage_deals`

Tablero **kanban** de negocios (deals) organizados por etapa. Es donde se mueve
cada oportunidad hasta ganarla o perderla. Al activar el CRM se crea un pipeline
*Ventas* con las etapas **Nuevo, Contactado, Propuesta, Ganado y Perdido**.

## El tablero

- Cada columna es una **etapa**; cada tarjeta, un **negocio**.
- **Arrastrar** una tarjeta a otra columna cambia su etapa.
- Al soltarla en *Ganado* o *Perdido*, el negocio se marca como ganado o perdido;
  en cualquier otra columna, queda abierto.
- Puedes **quitar** una tarjeta del tablero sin borrar el negocio (se conserva su historial).

## Campos del negocio

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Título** | requerido | Nombre del negocio. |
| **Etapa** | requerido | Columna del tablero. |
| **Estado** | — | Abierto, Ganado o Perdido. |
| **Contacto** | — | Persona asociada. |
| **Empresa** | — | Organización asociada. |
| **Valor** | — | Monto estimado. |
| **Moneda** | — | Moneda del valor. |
| **Responsable** | — | Usuario a cargo. |
| **Equipo** | — | Equipo asignado. |
| **Cierre estimado** | — | Fecha prevista de cierre. |
| **En el pipeline** | — | Si se apaga, la tarjeta desaparece del tablero; el negocio y su historial se conservan. |

## Ciclo de vida

```text
open  →  won   (se gana)
      →  lost  (se pierde)
```

Al pasar a ganado o perdido se registra la fecha de cierre automáticamente.

> [!TIP]
> Usa **Actividades** para agendar el próximo paso de cada negocio: el tablero
> muestra el estado, las actividades te dicen qué hacer después.
