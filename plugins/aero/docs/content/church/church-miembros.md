---
title: Miembros, familias y sedes
sort: 10
---
# Miembros, familias y sedes

**Ruta:** `Iglesia → Miembros`, `Familias` y `Sedes`
**Controladores:** `Members`, `Families`, `Branches`
**Modelos:** `Member`, `Family`, `Branch`
**Permiso:** `aero.church.use`, `aero.church.pastor`, `aero.church.secretary` (Sedes: solo administración)

## Para qué sirve

Es el registro de personas de la iglesia. Cada persona tiene un **estado**, puede pertenecer a una **familia** y a una **sede**, y guarda sus **consentimientos** para recibir mensajes y para el uso de sus datos.

## Campos de un miembro

| Campo | Descripción |
|-------|-------------|
| **Nombres / Apellidos** | Obligatorio el nombre. |
| **Estado** | Visitante, nuevo asistente, miembro activo o inactivo. |
| **Sede** | A qué sede pertenece. |
| **Documento, género, nacimiento, estado civil, aniversario** | Datos personales; el nacimiento permite cumpleaños y calcular si es menor. |
| **Teléfono (WhatsApp) y correo** | El teléfono se limpia automáticamente. |
| **Familia y rol en la familia** | Cabeza de familia, cónyuge, hijo/a u otro. |
| **Tutor** | Otro miembro responsable, para menores de 18 años. |
| **Miembro desde, bautizado, fecha de bautismo** | Historial de la persona. |
| **Acepta mensajes / Autoriza el uso de datos** | Consentimientos; al activarlos se registra la fecha. |
| **Observaciones** | Notas internas. |

## Reglas

- Los **menores** (menos de 18 años) nunca reciben mensajes en su propio teléfono: el aviso va al tutor, si el tutor aceptó mensajes. Tampoco aparece su contacto en los reportes.
- Las familias agrupan a los integrantes; un miembro sin familia es válido.
- Las **sedes** permiten separar miembros, grupos, eventos y aportes por ubicación.

## Acceso al portal

En la ficha de un miembro adulto con correo, el botón **Crear acceso al portal** crea (o vincula) su cuenta y le envía un correo para elegir su contraseña. Ver [Sitio público y portal](church-sitio-publico).

> [!NOTE]
> Los cambios en miembros quedan en la [auditoría](church-reportes-auditoria) (solo los nombres de los campos modificados, no sus valores).

**Versión documentada:** 1.6.0
