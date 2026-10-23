---
title: Seguimiento de visitantes
sort: 50
---
# Seguimiento de visitantes

**Ruta:** `Iglesia → Visitantes`
**Controlador:** `Visits`
**Modelos:** `Visit`, `VisitContact`
**Permiso:** `aero.church.use`, `pastor`, `secretary`

## Formulario de bienvenida con QR

En *Configuración → Visitantes y oración* activa **Formulario de bienvenida público**. Tu sitio publica la página `/bienvenida` y la pantalla muestra su **código QR** para imprimir. El visitante deja nombre, teléfono o correo, qué le interesa y sus autorizaciones.

> [!IMPORTANT]
> El formulario exige la autorización para guardar los datos y un teléfono o correo. Está protegido contra envíos automáticos.

## Seguimiento

| Campo | Descripción |
|-------|-------------|
| **Estado** | Nuevo, contactado, invitado, integrado o sin continuidad. |
| **Responsable del seguimiento** | Miembro que lo contacta. |
| **Grupo de interés, cómo supo, interés** | Datos de la visita. |
| **Próxima acción** | Fecha para el siguiente contacto. |

Cada **contacto** (llamada, WhatsApp, visita u otro) queda registrado con su nota y próxima acción; el primero pasa al visitante de *nuevo* a *contactado*.

El botón **Integrar como miembro** crea su ficha de miembro nuevo con sus consentimientos. La lista muestra las estadísticas del año y el porcentaje de integración.

**Versión documentada:** 1.6.0
