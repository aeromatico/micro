# Hello — Vista general

**Hello** es el centro de mensajería de tu sitio: conecta tus cuentas de
WhatsApp y redes, y desde un solo panel conversas, envías mensajes, gestionas
plantillas y publicas en redes sociales.

Todo funciona por sitio (tenant): tus cuentas, contactos y conversaciones están
aislados. Los perfiles y cuentas conectadas de la plataforma son del
superadmin; el día a día lo administras aquí.

## Menú

| Sección | Para qué sirve |
|---------|----------------|
| **Conectar cuenta** | Vincular un número de WhatsApp por QR o Cloud API. |
| **Redactar** | Enviar un mensaje manual o masivo. |
| **Bandeja de conversaciones** | Leer y responder chats. |
| **Llamadas** | Historial de llamadas y grabaciones. |
| **Contactos** | Personas con las que te escribes. |
| **Plantillas** | Mensajes reutilizables de WhatsApp. |
| **Redes sociales** | Publicar en tus cuentas conectadas. |

## Flujo de mensajería

```text
Conectas un número (QR / Cloud API)
        ▼
Llegan conversaciones → respondes en la bandeja
        ▼
Envías mensajes manuales o masivos desde "Redactar"
```

## Guías de cada función

- [Conectar cuenta](hello-connect) — vincular WhatsApp.
- [Redactar](hello-compose) — envío manual y masivo.
- [Bandeja de conversaciones](hello-conversations) — atender chats.
- [Llamadas](hello-calls) — historial y grabaciones.
- [Contactos](hello-contacts) — identidades por plataforma.
- [Plantillas](hello-templates) — mensajes reutilizables.
- [Publicaciones](hello-posts) — redes sociales.

## Permisos

| Permiso | Desbloquea |
|---------|-----------|
| `aero.hello.manage_accounts` | Conectar cuentas |
| `aero.hello.manage_conversations` | Redactar y bandeja |
| `aero.hello.manage_calls` | Llamadas |
| `aero.hello.manage_contacts` | Contactos |
| `aero.hello.manage_templates` | Plantillas |
| `aero.hello.manage_posts` | Redes sociales |
| `aero.hello.superadmin` | Perfiles y cuentas de la plataforma (superadmin) |
