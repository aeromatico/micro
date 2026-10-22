---
title: Emisores
sort: 10
featured: false
---
# Emisores

**Ruta:** `/backend/aero/invoicing/issuers` · **Permiso:** `aero.invoicing.use`

Un emisor es el NIT (con su sucursal y punto de venta) con el que tu negocio emite facturas.

## Campos

| Campo | Descripción |
| --- | --- |
| Nombre interno | Para reconocerlo en listas, por ejemplo «Casa matriz». |
| Activo | Interruptor; activo por defecto. |
| NIT del emisor | NIT con el que se factura. |
| Razón social | Nombre legal. |
| Nombre comercial | Nombre con el que se conoce el negocio. |
| Teléfono | Teléfono del emisor. |
| Dirección | Dirección del emisor. |
| Municipio | Municipio del emisor. |
| Código de sucursal | `0` = casa matriz. Debe coincidir con lo registrado en el SIN. |
| Código de punto de venta | `0` = punto por defecto. Los demás se registran antes en el SIN. |
| Modalidad | Modalidad de facturación (lista). Por defecto, la primera. |
| Código de sistema | Lo asigna el SIN al registrar el sistema. Si no lo tienes, déjalo vacío. |

> [!WARNING]
> Los códigos de sucursal y de punto de venta deben existir en el SIN. Si no coinciden, el proveedor rechazará la factura.

## Qué no ves

La sección «Proveedor» (driver, ambiente y credencial SIAT) es solo de la plataforma.

**Versión documentada:** 1.1.0
