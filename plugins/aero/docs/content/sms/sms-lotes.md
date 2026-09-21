# Función: Lotes

**Ruta:** `SMS → Lotes`
**Controlador:** `Aero\Sms\Controllers\Batches`
**Modelo:** `Aero\Sms\Models\Batch`
**Permiso:** `aero.sms.use`

Cada envío **masivo** genera un lote. Aquí ves su **progreso** y el detalle de
los mensajes que contiene. La ficha es de **solo lectura**.

## Ficha del lote

| Campo | Para qué sirve |
|-------|----------------|
| **ID público** | Identificador del lote. |
| **Estado** | Situación del lote. |
| **Nombre** | Nombre del envío. |
| **API key** | Credencial que lo originó. |
| **Destinatarios** | Cuántos números incluye. |
| **Créditos cobrados** | Costo total. |
| **Programado** | Cuándo se programó. |
| **Terminado** | Cuándo finalizó. |

## Progreso

El avance se muestra como **enviados / total** y termina con estado
`completed` cuando no queda ningún mensaje en cola. Desde el lote puedes revisar
sus mensajes individuales.

> [!TIP]
> Si un lote programado ya no lo quieres, revisa que no esté en curso antes de
> asumir que se puede detener: los mensajes ya encolados siguen su curso.
