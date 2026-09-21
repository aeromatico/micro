# Formulario: Plantillas

**Ruta:** `SMS → Plantillas`
**Controlador:** `Aero\Sms\Controllers\Templates`
**Modelo:** `Aero\Sms\Models\Template`
**Permiso:** `aero.sms.use`

Mensajes reutilizables para no reescribir el mismo texto en cada envío. Soportan
**variables** que se reemplazan por los datos de cada destinatario.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | — | Nombre para reconocerla. |
| **Código** (`slug`) | — | Lo que envía el consumidor en el campo `template`. Se propone desde el nombre. |
| **Activa** | — | Solo las activas se ofrecen al enviar. |
| **Texto** | — | Cuerpo del mensaje, con variables `{{nombre}}`. |

## Variables

```text
Hola {{nombre}}, tu código es {{codigo}}.
```

Las variables se completan con las columnas del **CSV** o con los datos del
destinatario (ver [Enviar](sms-enviar)).

> [!TIP]
> Usa nombres de código cortos y estables (`bienvenida`, `codigo-otp`): si los
> integras por API, el código es lo que envía tu sistema.
