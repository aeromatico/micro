---
title: WordPress Flash — vista general
sort: 1
featured: false
---
# WordPress Flash — vista general

WordPress Flash es un motor de sitio alternativo para quienes ya están acostumbrados a WordPress y WooCommerce. Al activarlo, tu cuenta recibe un sitio propio dentro de nuestro WordPress Multisite y tu subdominio pasa a mostrar ese sitio en lugar del sitio de la plataforma Aero.

## Qué hace

- **Crea tu sitio de WordPress automáticamente**, con un usuario administrador y una contraseña generados para ti.
- **Apunta tu subdominio a WordPress**: no tienes que cambiar nada en DNS.
- **Sincroniza tu catálogo con Tienda (Aero.Shop)**: los productos y clientes de WooCommerce se copian a Aero.Shop en tiempo real y, como respaldo, se reconcilian cada 15 minutos.

## Cómo funciona la sincronización

La sincronización es en **un solo sentido**: WooCommerce manda siempre. Si editas un producto o un cliente en WordPress, Aero.Shop se actualiza con esos datos y sobrescribe lo que hubiera allí.

| Elemento | Qué se sincroniza |
|---|---|
| Productos | Nombre, SKU, descripción, precio, precio de oferta, inventario, estado (publicado o borrador), categoría y la primera imagen (solo al crear). Si borras el producto en WordPress, se elimina también su vínculo en Aero.Shop. |
| Clientes | Correo, nombre, apellido y teléfono. Si ya existe un cliente con el mismo correo en Aero.Shop, se reutiliza en vez de duplicarlo. |

> [!NOTE]
> Si tu cuenta no tiene Aero.Shop, WordPress Flash sigue funcionando para servir tu sitio; solo se omite la sincronización de catálogo.

## Fuera de alcance

- Pedidos hacia WordPress.
- Dominios propios externos (fuera de la plataforma).
- Contenido y páginas, cupones y reseñas de WooCommerce.

## Dónde está

En el menú **WordPress Flash** de tu panel. Ver [Motor del sitio](wpflash-motor-del-sitio).

**Versión documentada:** 1.0.0
