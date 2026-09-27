# Formulario: Activos

**Ruta:** `Tracking → Activos`
**Controlador:** `Aero\Tracking\Controllers\Assets`
**Modelo:** `Aero\Tracking\Models\Asset`
**Permiso:** `aero.tracking.use`

Un **activo** es lo que se rastrea: un vehículo, una persona, cualquier
dispositivo con GPS. Cada activo recibe una URL propia a la que el dispositivo
envía sus posiciones.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Cómo identificas el activo (ej. «Moto 1»). |
| **Tipo** | requerido | Texto libre: `motorcycle`, `truck`, `bus`, `person`… |
| **Placa / código** | — | Identificador corto (placa, número interno). |
| **Activo** | — | Solo los activos vigentes aparecen en el mapa. |

## URL para OwnTracks

Al editar un activo verás **URL para OwnTracks** (solo lectura). Es la dirección
a la que el dispositivo envía sus posiciones.

Configuración en la app OwnTracks:

1. Ajustes → **Conexión**.
2. Modo **HTTP**.
3. Pega la **URL para OwnTracks** del activo.

## Estado del activo

| Estado | Cuándo |
|--------|--------|
| **En línea** | Envió una posición en los últimos 5 minutos. |
| **Sin señal** | Está activo pero lleva más de 5 minutos sin enviar. |
| **Inactivo** | Lo desactivaste (no aparece en el mapa). |

> [!WARNING]
> La URL para OwnTracks es **secreta**: quien la tenga puede enviar posiciones
> de este activo. No la compartas ni la pegues en lugares públicos.
