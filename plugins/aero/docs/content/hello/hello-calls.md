# Función: Llamadas

**Ruta:** `Hello → Llamadas`
**Controlador:** `Aero\Hello\Controllers\Calls`
**Modelo:** `Aero\Hello\Models\Call`
**Permiso:** `aero.hello.manage_calls`

Historial de llamadas de WhatsApp: entrantes y salientes, con su duración,
grabación, transcripción y costos. La ficha es de **solo lectura**.

## Listado

| Columna | Para qué sirve |
|---------|----------------|
| **Inicio** | Cuándo comenzó. |
| **Dirección** | Entrante o saliente. |
| **De / Para** | Números involucrados. |
| **Estado** | Resultado de la llamada. |
| **Fin** | Motivo de finalización. |
| **Duración** | Cuánto duró. |
| **Costo (USD)** | Costo de plataforma de la llamada. |

## Ficha de la llamada

| Campo | Para qué sirve |
|-------|----------------|
| **Dirección / Estado** | Contexto de la llamada. |
| **De / Para** | Números. |
| **Destino de reenvío** | A dónde se enrutó (`tel:`, `sip:` o agente de IA `wss://`). |
| **Inicio / Fin / Duración** | Tiempos. |
| **Motivo de fin** | `hangup`, `no_answer`, `rejected`, `error`. |
| **Grabación** | Reproductor de la grabación (se pide fresca al escuchar). |
| **Transcripción** | Diálogo por turnos. |
| **Costos** | Costo de plataforma, cobrado por Meta e informativo total. |

> [!NOTE]
> Los costos de la plataforma y los de Meta son **facturas distintas**: Meta te
> cobra directo a tu cuenta de WhatsApp Business; el otro es lo que cobra la
> plataforma.

> [!TIP]
> Las URLs de grabación del proveedor expiran en pocos minutos; el botón de
> escucha pide una nueva en el momento, así siempre funciona.
