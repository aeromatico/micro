# Formulario: Respuestas rápidas

**Ruta:** `CRM → Respuestas`
**Controlador:** `Aero\Crm\Controllers\QuickReplies`
**Modelo:** `Aero\Crm\Models\QuickReply`
**Permiso:** `aero.crm.manage_quick_replies`

Mensajes predefinidos que el equipo puede insertar en el chat escribiendo un
**atajo**. Ahorran tiempo y mantienen un tono consistente. Pueden ser texto simple
o mensajes con botones, menú, enlace, ubicación, contacto o encuesta.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Título** | requerido | Nombre interno para reconocerla. |
| **Atajo** | requerido, único por sitio | Se invoca escribiendo `/atajo` (ej. `/catalogo`). Minúsculas, sin espacios. |
| **Área** | — | General, Ventas, Soporte, Cobranzas o Postventa. |
| **Tipo de mensaje** | requerido (por defecto Texto) | Cómo se envía: ver [Tipos de mensaje](#tipos-de-mensaje). |
| **Orden** | — | Menor número aparece primero (por defecto 10). |
| **Veces usada** | solo lectura | Sube cada vez que se invoca desde el chat. |
| **Activa** | — | Solo las activas se ofrecen en el chat. |
| **Cuentas Hello** | opcional | Sin marcar ninguna, la respuesta aparece en todas las cuentas; si marcas algunas, solo en las conversaciones de esas cuentas. |
| **Mensaje (texto principal)** | requerido, salvo en Ubicación, Contacto y Encuesta | El texto que se inserta; en botones y menú es el texto principal. |
| **Plantilla de WhatsApp (nombre)** | opcional | Ver [Plantilla de WhatsApp](#plantilla-de-whatsapp). |
| **Idioma de la plantilla** | opcional | Código de idioma (por defecto `es`). |
| **Categoría de la plantilla** | opcional | Utility o Marketing. |

## El atajo

- Puedes escribirlo con o sin la barra y con mayúsculas: se normaliza a
  minúsculas sin `/`.
- Solo admite minúsculas, números, guion y guion bajo (máximo 40 caracteres).
- Es **único dentro de tu sitio**, así que dos respuestas no pueden compartir el
  mismo atajo.

## Tipos de mensaje

Al elegir el **Tipo de mensaje** aparecen los campos propios de ese tipo.

| Tipo | Campos adicionales |
|------|--------------------|
| **Texto** | Ninguno. |
| **Botones de respuesta** (Cloud API) | De 1 a 3 botones, máximo 20 caracteres cada uno. |
| **Menú desplegable** (Cloud API) | Texto del botón del menú, título de la sección (opcional) y hasta 10 opciones (título de hasta 24 caracteres y descripción opcional). |
| **Botón de enlace** (Cloud API) | Texto del botón y enlace (`https://…`). |
| **Pedir ubicación** (Cloud API) | Solo el mensaje. |
| **Botón de llamada** (Cloud API) | Texto del botón de llamada (opcional). |
| **Ubicación** (WhatsApp Web) | Coordenadas `lat, lng` o enlace de Google Maps, y nombre del lugar (opcional). |
| **Contacto** (WhatsApp Web) | Nombre y teléfono del contacto a compartir. |
| **Encuesta** (WhatsApp Web) | Pregunta, de 2 a 12 opciones y si se permite elegir varias. |

> [!NOTE]
> Si la cuenta que envía no admite el tipo elegido (por ejemplo, botones en
> WhatsApp Web), se envía el **texto de respaldo** con las opciones escritas.

## Plantilla de WhatsApp

Opcional. Indica el nombre exacto de una plantilla ya aprobada en Meta, sin
variables y con este mismo texto. Cuando pasaron más de 24 horas desde el último
mensaje del contacto, se envía esa plantilla (conservando los saltos de línea) en
lugar del texto genérico. El nombre solo admite minúsculas, números y guion bajo.
La **categoría** (Utility o Marketing) es informativa: la define Meta al aprobarla.

> [!TIP]
> Agrupa por **Área** (ventas, soporte, cobranzas) para encontrar la respuesta
> correcta rápido cuando el catálogo crezca.

**Versión documentada:** 1.14.0
