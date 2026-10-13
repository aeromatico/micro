---
title: Clientes
sort: 40
---
# Clientes

Ficha de las personas que reservan en tu negocio, con su historial de citas.

**Ruta:** `Reservas → Clientes` (`/backend/aero/office/customers`)
**Controlador:** `Aero\Office\Controllers\Customers`
**Modelo:** `Aero\Office\Models\Customer`
**Permiso:** `aero.office.use` o `aero.office.reception`

## Campos

| Campo | Descripción |
|-------|-------------|
| Nombre (obligatorio) | Nombre del cliente. |
| Documento | Documento de identidad. |
| Teléfono | Número al que llegan los avisos por WhatsApp. |
| Correo | Correo para avisos y para la cuenta del portal. |
| Observaciones generales | Notas internas. |

## Acceso al portal

Desde la ficha puedes **crear o vincular el usuario** del cliente: queda habilitado para entrar al portal con su correo y ver sus reservas en "Mis reservas". Los clientes que reservan en línea reciben su cuenta automáticamente (ver [Portal público](office-portal-publico)).

## Comportamiento

- Al reservar, un cliente con el mismo correo o teléfono se reutiliza en lugar de duplicarse.
- Si tienes CRM activo, cada cliente se refleja como contacto del CRM (se enlaza por correo o teléfono, o se crea).
- Los clientes son propios de tu negocio: otro negocio no los ve.

**Versión documentada:** 1.1.0
