---
title: Motor del sitio
sort: 10
featured: false
---
# Motor del sitio

- **Ruta:** `/backend/aero/wpflash/siteengine`
- **Controlador:** `Aero\WpFlash\Controllers\SiteEngine`
- **Modelo:** `Aero\WpFlash\Models\SiteInstance`
- **Permiso:** `aero.wpflash.manage_sites` (Gestionar sitios WordPress Flash)

Pantalla única donde eliges con qué motor se sirve tu sitio: la **plataforma Aero** (Sitio + Tienda) o **WordPress Flash**. No es un formulario con campos: son botones que activan, reintentan o desactivan el servicio.

## Estados de la pantalla

| Estado | Qué ves | Acciones |
|---|---|---|
| Sin sitio, o suspendido | «Motor actual: Plataforma Aero». Si antes lo tuviste, se indica que el sitio de WordPress sigue existiendo pero desconectado. | **Activar WordPress Flash** (pide confirmación). |
| Creando | «Creando el childsite de WordPress…»; puede tardar unos minutos. | Actualizar la página. |
| Error | Aviso con el motivo del fallo. | **Reintentar**. |
| Activo | «Motor actual: WordPress Flash» con enlace a tu dominio; fecha de la última sincronización si existe. | **Ver credenciales de acceso a WordPress**, **Volver a la plataforma Aero**. |

## Activar WordPress Flash

Al activar, el sistema:

1. Crea un usuario administrador de WordPress (`wpflash_<id>`) con contraseña aleatoria.
2. Crea tu sitio en la red de WordPress, usando el identificador (handle) de tu cuenta como subdominio.
3. Configura la conexión con WooCommerce y registra los avisos (webhooks) de productos y clientes.
4. Apunta tu subdominio al sitio de WordPress.

Al terminar se muestran **usuario, contraseña y enlace al panel** (`/wp-admin`).

> [!IMPORTANT]
> Guarda las credenciales cuando aparezcan. Después no se muestran en claro, salvo que pulses **Ver credenciales de acceso a WordPress**.

> [!WARNING]
> Si tu plan no incluye WordPress Flash, la activación se rechaza con el mensaje «Tu plan actual no incluye WordPress Flash.».

> [!NOTE]
> Si el identificador de tu cuenta ya existe como sitio en la red de WordPress, el alta falla y el estado pasa a «Error». Usa **Reintentar** o contacta a soporte.

## Volver a la plataforma Aero

Revierte el apuntado del subdominio: tu sitio vuelve a servirse con la plataforma normal. El sitio de WordPress **no se borra**, solo queda desconectado, así que puedes reactivarlo más adelante sin perder el catálogo cargado.

## Sincronización

Ver [vista general](wpflash-vista-general) para el detalle de qué datos pasan de WooCommerce a Aero.Shop.

**Versión documentada:** 1.0.0
