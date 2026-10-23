---
title: Comunicados por WhatsApp
sort: 70
---
# Comunicados por WhatsApp

**Ruta:** `Iglesia → Comunicados`
**Controlador:** `Announcements`
**Modelos:** `Announcement`, `AnnouncementRecipient`
**Permiso:** `aero.church.use`, `pastor`, `secretary`

## Crear y enviar

1. Escribe el **título** (interno), el **tipo** y el **mensaje**. Usa `{nombre}` para saludar por su nombre.
2. Elige la **audiencia**: estados (por defecto activos y nuevos), sede, grupo o inscritos a un evento.
3. Antes de enviar verás **cuántas personas lo recibirían** y cuántas quedan fuera.
4. **Enviar por WhatsApp** (con confirmación). No se puede enviar dos veces ni editar después.

El historial muestra, por destinatario, si salió o falló.

## Reglas de privacidad

- Solo reciben quienes **aceptaron mensajes** y tienen teléfono.
- A un **menor** no se le escribe a su teléfono: el aviso va a su tutor, si consintió.
- Un mismo teléfono recibe el mensaje **una sola vez**.

## Cumpleaños y avisos automáticos

En *Configuración → Comunicación*:

- **Felicitar cumpleaños** (apagado por defecto): cada día a las 08:00, solo a adultos con consentimiento; usa tu plantilla con `{nombre}`.
- **Avisar cambios en eventos**: a los inscritos si un evento cambia de horario, lugar o se cancela.

> [!WARNING]
> Se necesita una cuenta de WhatsApp conectada en Hello. Sin ella el envío se rechaza con un mensaje claro y no sale nada.

**Versión documentada:** 1.6.0
