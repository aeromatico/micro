# Función: Mensajes

**Ruta:** `SMS → Mensajes`
**Controlador:** `Aero\Sms\Controllers\Messages`
**Modelo:** `Aero\Sms\Models\Message`
**Permiso:** `aero.sms.use`

Historial de cada SMS enviado, con su **estado de entrega** y los datos del
envío. La ficha es de **solo lectura**.

## Filtros

| Filtro | Para qué sirve |
|--------|----------------|
| **Estado** | Ver solo los que están en cola, enviados, entregados, fallidos, etc. |

## Ficha del mensaje

| Campo | Para qué sirve |
|-------|----------------|
| **ID público** | Identificador del mensaje. |
| **Estado** | Situación actual del envío. |
| **Tenant / API key** | De quién y desde qué credencial salió. |
| **Destino / Referencia** | A quién y con qué referencia. |
| **Texto** | Contenido enviado. |
| **Segmentos / Codificación** | Cómo se dividió y codificó. |
| **Créditos cobrados** | Lo que costó. |
| **Proveedor / ID del proveedor** | Con quién y su identificador. |
| **Error** | Motivo si falló. |
| **Enviado / Entregado** | Fechas de cada paso. |

## Estados

| Estado | Significado |
|--------|-------------|
| `queued` | En cola. |
| `sending` | Enviando. |
| `sent` | Enviado. |
| `delivered` | Entregado. |
| `failed` | Fallido. |
| `undelivered` | No entregado. |
| `blocked` | Bloqueado (el número está en bajas). |
| `cancelled` | Cancelado. |

> [!TIP]
> Revisa los **fallidos** y **no entregados**: suelen indicar números inválidos
> o sin cobertura, útiles para depurar tu lista.
