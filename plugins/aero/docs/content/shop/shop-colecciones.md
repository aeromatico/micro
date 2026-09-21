# Formulario: Colecciones

**Ruta:** `Tienda → Colecciones`
**Controlador:** `Aero\Shop\Controllers\Collections`
**Modelo:** `Aero\Shop\Models\Collection`
**Permiso:** `aero.shop.manage_collections`

Agrupa productos para organizar el catálogo (categorías, temporadas, ofertas) y
para navegar por secciones. Las colecciones pueden anidarse.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Nombre de la colección. |
| **Slug** | — | URL. Vacío = se genera del nombre. |
| **Colección padre** | — | Para anidar dentro de otra colección. |
| **Activa** | — | Si está apagada, no se muestra. |
| **Descripción** | — | Texto descriptivo. |
| **Imagen** | — | Imagen de portada (600×400). |

> [!TIP]
> Combina colecciones padre/hijo para armar una navegación tipo
> *Ropa → Camisas / Pantalones*.
