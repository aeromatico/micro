# Formulario: Contactos

**Ruta:** `Hello → Contactos`
**Controlador:** `Aero\Hello\Controllers\Contacts`
**Modelo:** `Aero\Hello\Models\Contact`
**Permiso:** `aero.hello.manage_contacts`

Personas con las que te escribes. **No se crean a mano**: si el CRM está
instalado, es la fuente única (se sincronizan solos); si no, entran
automáticamente con los mensajes entrantes. Aquí editas sus identidades,
bloqueo y notas.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Nombre del contacto. |
| **Bloqueado** | — | Evita tratar/responder sus mensajes. |
| **Notas** | — | Observaciones internas. |
| **Identidades por plataforma** | — | Repetidor: cada red y su número/handle/ID. |

## Identidades

Cada contacto puede tener varias identidades, una por plataforma: WhatsApp, SMS,
Instagram, Facebook, Twitter/X, LinkedIn, TikTok, Bluesky, Discord, Pinterest,
Google Business, Snapchat, YouTube, Reddit y Threads. Es lo que permite
reconocer a la misma persona en distintos canales.

> [!NOTE]
> El nombre solo se completa solo cuando es un placeholder (vacío, un número o
> *Contacto #n*). Si alguien ya lo escribió a mano, nunca se pisa.

> [!WARNING]
> Al intentar crear un contacto desde aquí, el sistema te redirige: usa
> **CRM → Contactos** (si el CRM está instalado) o espera a que la persona te
> escriba.
