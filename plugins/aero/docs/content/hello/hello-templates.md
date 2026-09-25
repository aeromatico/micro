# Formulario: Plantillas

**Ruta:** `Hello → Plantillas`
**Controlador:** `Aero\Hello\Controllers\Templates`
**Modelo:** `Aero\Hello\Models\Template`
**Permiso:** `aero.hello.manage_templates`

Mensajes reutilizables de WhatsApp. Sirven para responder rápido y, sobre todo,
para **escribir fuera de la ventana de 24 h** (Cloud API exige una plantilla
aprobada por Meta).

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Nombre interno de la plantilla. |
| **Canal** | — | Solo WhatsApp (el menú maneja únicamente plantillas de WhatsApp). |
| **Mensaje** | requerido | Cuerpo del mensaje. Usa variables entre llaves dobles, ej. `{{name}}`. |
| **Nombre de plantilla aprobada (proveedor)** | — | Para cuentas Hello + WhatsApp Cloud API, que exigen una plantilla aprobada por Meta. Las cuentas de WhatsApp Web la ignoran. |
| **Idioma de la plantilla** | — | Código de idioma, ej. `es`. |
| **Variables disponibles** | — | Lista informativa de las variables usadas. |

## Variables

```text
Hola {{name}}, tu pedido {{order}} está listo para retirar.
```

- Se escriben entre llaves dobles.
- En Cloud API, las variables deben coincidir con las de la plantilla aprobada
  por Meta.

> [!IMPORTANT]
> Si usas **Cloud API**, la plantilla debe estar aprobada por Meta y aquí debes
> indicar su **nombre** y **idioma**. En **WhatsApp Web** el texto se envía tal cual.

> [!TIP]
> Mantén nombres de plantilla descriptivos y una por caso de uso (pedido listo,
> recordatorio de pago, bienvenida).
