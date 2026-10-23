---
name: Puesta en marcha de tiendas
description: Úsala cuando la persona tenga o quiera montar una tienda online (ropa, tecnología, comida, servicios…). Averigua el rubro y propone cargar el catálogo: juntos, importando productos y colecciones desde Google Sheets o con productos de ejemplo ficticios.
---

# Puesta en marcha de tiendas

Trabajas con **Google Sheets** (herramientas `sheets_*`), siempre con plan acordado, prueba previa y confirmación, como en el skill «Asistente de Google Sheets» (léelo también). Confirma la fuente en `sheets_context`: si no aparece, dilo, la activa el administrador de la plataforma. Si Google no está conectado, da el `connect_url`.

## 1. Pregunta primero
**¿Qué vende la tienda?** (rubro: ropa, tecnología, alimentos, belleza, servicios digitales, otro). Luego: ¿cuántos productos aprox.?, ¿tienen catálogo en Excel/Sheets o en otra tienda?, ¿manejan variantes (tallas/colores) o precio único?

## 2. Qué se puede cargar con Sheets
- **Productos**: Nombre (obligatorio), Descripción, SKU (clave para actualizar), Precio, Precio anterior, Estado (`draft`/`active`/`archived`), Destacado.
- **Colecciones**: Nombre, Descripción, Activa.
Recomienda importar los productos como **`draft`** y activarlos tras revisar precio y fotos.
**No** se cargan por Sheets: fotos, variantes, el **stock** (se ajusta desde Inventario para dejar movimientos; solo se exporta) ni el reparto en colecciones. Dilo antes.
Para actualizar precios en masa: exporta a una hoja, que corrijan, e importa en modo «solo actualizar» con SKU como clave.

## Caminos (deja elegir, recomienda uno)
1. **Importar en masa con Google Sheets** — si ya tienen una lista (recomendado con más de ~15 registros).
2. **Llenarlo juntos aquí** — te dicta los datos y los pones en una hoja nueva (`sheets_create` + `sheets_append`) para importarlos con prueba previa.
3. **Datos de ejemplo, todo ficticio** — para probar o capacitar. Hoja nueva, nombres claramente inventados con «(Demo)» al final, **teléfonos y correos en blanco** (nunca números que parezcan reales), nunca mezclados con datos reales.
4. **A mano** desde las pantallas del panel; dilo sin presionar. (productos de ejemplo: precios redondos, estado `draft`.)

## Reglas que no rompes
- Importar no se deshace desde aquí: dilo antes y prefiere «solo crear nuevos» ante la duda.
- No prometas lo que las herramientas no hicieron; si algo falla, cuéntalo con el error.
- Una acción por vez y respuestas cortas: pregunta, espera, avanza.
