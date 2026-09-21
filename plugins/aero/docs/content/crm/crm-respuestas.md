# Formulario: Respuestas rápidas

**Ruta:** `CRM → Respuestas`
**Controlador:** `Aero\Crm\Controllers\QuickReplies`
**Modelo:** `Aero\Crm\Models\QuickReply`
**Permiso:** `aero.crm.manage_quick_replies`

Mensajes predefinidos que el equipo puede insertar en el chat escribiendo un
**atajo**. Ahorran tiempo y mantienen un tono consistente.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Título** | requerido | Nombre interno para reconocerla. |
| **Atajo** | requerido, único por sitio | Se invoca escribiendo `/atajo` (ej. `/catalogo`). Minúsculas, sin espacios. |
| **Área** | — | General, Ventas, Soporte, Cobranzas o Postventa. |
| **Orden** | — | Menor número aparece primero (por defecto 10). |
| **Veces usada** | solo lectura | Sube cada vez que se invoca desde el chat. |
| **Activa** | — | Solo las activas se ofrecen en el chat. |
| **Mensaje** | requerido | El texto que se inserta. |

## El atajo

- Puedes escribirlo con o sin la barra y con mayúsculas: se normaliza a
  minúsculas sin `/`.
- Es **único dentro de tu sitio**, así que dos respuestas no pueden compartir el
  mismo atajo.

> [!TIP]
> Agrupa por **Área** (ventas, soporte, cobranzas) para encontrar la respuesta
> correcta rápido cuando el catálogo crezca.
