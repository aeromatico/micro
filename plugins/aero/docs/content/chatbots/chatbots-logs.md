# Función: Registro de respuestas

**Ruta:** `Chatbots → Registro de respuestas`
**Controlador:** `Aero\Chatbots\Controllers\Logs`
**Modelo:** `Aero\Chatbots\Models\Log`
**Permiso:** `aero.chatbots.manage`

Historial **de solo lectura** de cada disparo automático del bot. Sirve para
auditar qué está contestando y por qué.

## Columnas

| Columna | Para qué sirve |
|---------|----------------|
| **Fecha** | Cuándo respondió (orden descendente). |
| **Bot** | Qué bot respondió. |
| **Tenant** | A qué sitio pertenece. |
| **Regla** | Qué regla coincidió. Si no coincidió ninguna, indica que fue el mensaje por defecto o la IA. |

## Para qué sirve

- Verificar que las reglas están respondiendo lo esperado.
- Detectar palabras clave que no están cubiertas (respuestas por defecto o fallback).
- Revisar la actividad del bot por fecha.

> [!TIP]
> Si ves muchas respuestas por defecto, revisa y amplía las
> [Reglas](chatbots-reglas) con las palabras que faltan.
