---
title: Facturación — vista general
sort: 1
featured: false
---
# Facturación electrónica (SIAT)

**Ruta:** `/backend/aero/invoicing/invoices` · **Permiso:** `aero.invoicing.use`

Facturación electrónica de Bolivia (SIAT). Cada negocio factura con su propio NIT; por detrás, la plataforma se comunica con el proveedor de facturación.

## Menú

| Entrada | Para qué sirve |
| --- | --- |
| **Facturas** | Ver las facturas del negocio, emitirlas y anularlas. |
| **Emisores** | Registrar los NIT, sucursales y puntos de venta con los que facturas. Ver [Emisores](invoicing-emisores). |

## Facturas

- Cada factura se puede **ver y descargar en PDF**, con su código QR y el importe en letras.
- Puedes **emitir y anular** tus facturas con el permiso `aero.invoicing.use`.
- El sistema guarda el CUFD (código único de facturación diaria) por emisor.

> [!IMPORTANT]
> Antes de emitir, registra al menos un emisor con los datos exactos que tienes en el SIN (NIT, sucursal, punto de venta y código de sistema).

## Qué no ves

El driver del proveedor, el ambiente (pruebas o producción) y las credenciales son de la plataforma: tú no los configuras.

**Versión documentada:** 1.1.0
