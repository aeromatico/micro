# Formulario: Configuración de contacto

**Ruta:** `Sitio Web → Configuración de sitio → pestaña Contacto`
**Controlador:** `Aero\Sites\Controllers\SiteSettings`
**Modelo:** `Aero\Sites\Models\ContactConfig`
**Permiso:** `aero.sites.manage_contact`

Guarda los datos públicos del negocio y el comportamiento del formulario de
contacto. Existe un registro `ContactConfig` por tenant.

## Pestaña Información

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Email de contacto** | email o vacío | Buzón público del negocio. |
| **Teléfono** | texto | Teléfono visible. Ej: `+591 70000000`. |
| **WhatsApp** | texto | Con código de país, sin espacios. Genera el enlace `wa.me`. |
| **Dirección** | texto | Dirección física. |

## Pestaña Ubicación

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Latitud** | numérico entre −90 y 90 | Ubicación en mapa. |
| **Longitud** | numérico entre −180 y 180 | Ubicación en mapa. |

`hasLocation()` solo devuelve verdadero cuando **ambas** coordenadas están
presentes.

## Pestaña Formulario

| Campo | Para qué sirve |
|-------|----------------|
| **Habilitar formulario de contacto** | Interruptor maestro del formulario del sitio. |
| **Mensaje de éxito** | Texto que ve el visitante tras enviar. |

## Cómo se usa

- El enlace de WhatsApp se normaliza quitando todo lo que no sea dígito:
  `+591 70000000` → `https://wa.me/59170000000`.
- Los envíos del formulario se registran como `ContactSubmission` y se pueden
  ver en la pestaña **Mensajes** de la configuración del sitio.
- Es uno de los *AI tools* que puede consultar el chatbot del sitio
  (`get_contact_info`).

> [!TIP]
> Deja el WhatsApp sin espacios y con código de país para que el enlace
> generado funcione directamente en móvil.
