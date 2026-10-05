---
title: Nodos de la Tienda
sort: 26
---
# Nodos de la Tienda: menú de categorías y productos

Dos nodos para ofrecer tu catálogo por chat. Están pensados para **conectarse**: el cliente ve un menú numerado, responde con un número (o el nombre) y recibe los productos de esa categoría.

Requieren que la **Tienda** esté activada en tu cuenta. Solo ven el catálogo de tu propia cuenta.

## Tienda › Menú de categorías

Arma el menú con tus categorías (colecciones) **activas**, ordenadas como las tienes en la tienda, y cuenta sus productos.

| Opción | Para qué sirve |
|--------|----------------|
| **Título del menú** | Por defecto «¿Qué te gustaría ver?». |
| **Texto al final** | Por defecto «Responde con el número o el nombre.». |
| **Subcategorías de…** | Vacío = categorías principales. Escribe el nombre o código de una categoría para mostrar sus subcategorías. |
| **Incluir categorías sin productos** | Por defecto **No**. |
| **Máximo de categorías** | Por defecto 10 (máx. 30). |
| **Guardar en la variable** | Por defecto `categorias`. |

Con la variable `categorias` puedes usar:

| Dato | Ejemplo |
|------|---------|
| `{{ vars.categorias.text }}` | El menú ya redactado, listo para un nodo **Responder** o **Enviar mensaje**. |
| `{{ vars.categorias.items }}` | La lista (número, id, nombre, cantidad de productos). |
| `{{ vars.categorias.count }}` | Cuántas categorías hay. |

**Salidas:** *con categorías* y *sin categorías* (por ejemplo, una tienda vacía).

## Tienda › Productos de una categoría

Lista los productos **activos y públicos** de la categoría elegida (y de sus subcategorías), ordenados por nombre, con precio en la moneda de tu tienda. Los productos internos del punto de venta no aparecen.

| Opción | Para qué sirve |
|--------|----------------|
| **Categoría elegida** | Vacío = lo que escribió el cliente. Acepta el **número del menú**, el **nombre** (sin importar mayúsculas ni tildes) o el **código**. |
| **ID de categoría (fijo)** | Para mostrar siempre la misma categoría. |
| **Buscar dentro** | Filtra por texto en nombre o descripción. |
| **Máximo de productos** | Por defecto 8 (máx. 20). |
| **Solo con stock** | Por defecto **No**: los agotados se marcan «(agotado)». |
| **Mostrar descripción** | Por defecto **Sí**. |
| **Texto al final** | Por ejemplo «Escribe menú para volver». |
| **Guardar en la variable** | Por defecto `productos`. |

Con la variable `productos`: `{{ vars.productos.text }}` (lista redactada), `.items` (nombre, precio, descripción, stock e imagen), `.category` (nombre de la categoría) y `.total`.

**Salidas:** *con productos*, *categoría vacía* y *no entendí*. En *no entendí* tienes `{{ vars.productos.menu_text }}` para reenviar el menú.

## Cómo se entienden al conectarlos

- Si el flujo tiene un nodo **Menú de categorías**, el nodo de productos usa **las mismas opciones** (subcategorías, incluir vacías, máximo). Así el «2» significa lo mismo que se le mostró al cliente, aunque la respuesta llegue en otra conversación o horas después.
- Si el menú y los productos corren en la misma ejecución, se usa el menú recién armado.
- El menú **no se guarda**: se recalcula, así que si cambias el orden de las categorías entre el menú y la respuesta, el número apuntará al nuevo orden.

## Flujo típico con un chat

1. Disparador **Mensaje entrante**.
2. **Condición** para distinguir si es el primer mensaje o una elección.
3. Primer mensaje → **Menú de categorías** → **Enviar mensaje** con `{{ vars.categorias.text }}`.
4. Elección → **Productos de una categoría** (déjalo vacío para leer lo que escribió el cliente) → **Enviar mensaje** con `{{ vars.productos.text }}`.
5. Desde *no entendí* → mensaje con `{{ vars.productos.menu_text }}`.

> [!TIP]
> El flujo **Ejemplo: catálogo de la tienda por chat** ya viene armado; se prueba con **Probar** y `{"texto":""}` (menú), `{"texto":"1"}` o `{"texto":"tazas"}`.

> [!IMPORTANT]
> Solo salen productos que pertenecen a una categoría. Un producto sin categoría no aparece en ningún menú.

> [!NOTE]
> Estos nodos muestran el catálogo; **no crean pedidos**.

**Versión documentada:** 1.1.0
