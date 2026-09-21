# Formulario: Reglas

**Ruta:** `Chatbots → Bots → (abrir un bot) → pestaña Reglas`
**Controlador:** `Aero\Chatbots\Controllers\Bots`
**Modelo:** `Aero\Chatbots\Models\Rule`

Las reglas son respuestas por **palabra clave**. Cuando un mensaje entrante
contiene una de las palabras, el bot responde el texto definido.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Prioridad** | — | A mayor número, más prioridad. Si dos reglas coinciden, gana la de mayor prioridad. |
| **Activa** | — | Solo las activas se evalúan. |
| **Palabras clave** | requerido | Si el mensaje contiene **cualquiera** de estas palabras, responde. |
| **Respuesta** | requerido | Texto que envía el bot. |

## Cómo coinciden

- La comparación es **sin distinguir mayúsculas** y por subcadena simple: si la
  palabra aparece en algún lugar del mensaje, la regla coincide.
- Se evalúan por **prioridad descendente**; la primera activa que coincida gana.
- Si ninguna coincide y el modo es **Chatbot**, se envía el mensaje por defecto
  del bot.

```text
prioridad 20: "precio"     → "Nuestros precios van desde..."
prioridad 10: "horario"    → "Atendemos de lunes a sábado..."
prioridad  0: "hola"       → "¡Hola! ¿En qué te ayudamos?"
```

> [!TIP]
> Usa prioridades altas para reglas específicas (promociones, derivación a un
> humano) y bajas para saludos generales.

> [!NOTE]
> El formato del texto también está limitado a lo que soporta WhatsApp:
> `*negrita*`, `_cursiva_`, `~tachado~` y `` `monoespaciado` ``.
