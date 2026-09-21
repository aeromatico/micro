# Formulario: Plantilla del sitio (Layout)

**Ruta:** `Sitio Web → Página de inicio → pestaña Plantilla`
**Controlador:** `Aero\Sites\Controllers\ContentEditor`
**Modelo:** `Aero\Sites\Models\Layout`
**Permiso:** `aero.sites.manage_pages`

Define el envoltorio HTML de todas las páginas del sitio: el `<head>`, el
header/navbar y el footer. Es el formulario más técnico del panel, y por eso
ofrece dos niveles de intervención: reemplazar piezas o reemplazar todo.

## Campo: Modo

| Modo | Qué reemplaza |
|------|---------------|
| **Plataforma** (`default`) | Mantiene las dependencias y el layout de la plataforma (Tailwind/Alpine propios). Solo permite personalizar header y footer. |
| **Plantilla propia** (`custom`) | Reemplaza **todo** el documento: `<head>`, dependencias CDN propias, header/footer y scripts. |

## En modo Plataforma

| Campo | Para qué sirve |
|-------|----------------|
| **Mostrar header / navbar** | Apágalo si armas el header como bloque dentro de cada página. |
| **Mostrar footer** | Apágalo si armas el footer como bloque dentro de cada página. |
| **Header / navbar propio** | Vacío = navbar del theme. Si escribes algo, reemplaza ese navbar en **todas** las páginas. |
| **Footer propio** | Vacío = footer del theme. |
| **Assets adicionales** | Se pega antes de `</head>`, después del CSS/JS de la plataforma. Útil para sumar una fuente o un script de terceros **sin** dejar de usar el stock. |

## En modo Plantilla propia

| Campo | Para qué sirve |
|-------|----------------|
| **Documento HTML completo** | Tu documento entero, con tus dependencias CDN. |

El HTML completo **debe** incluir dos marcadores:

```html
<!DOCTYPE html>
<html>
<head> ...tus dependencias... </head>
<body>
  <!-- AERO:CONTENT -->   <!-- obligatorio: aquí va el contenido de la página -->
  <!-- AERO:SCRIPTS -->   <!-- si lo omites, carrito y formulario de contacto dejan de funcionar -->
</body>
</html>
```

> [!WARNING]
> Si omites `<!-- AERO:CONTENT -->`, las páginas no renderizan contenido. Si
> omites `<!-- AERO:SCRIPTS -->`, el formulario de contacto y el carrito dejan
> de funcionar.

> [!TIP]
> Antes de cambiar a *Plantilla propia*, revisa la pestaña **Referencia**: allí
> se muestra el HTML que sirve hoy la plataforma, para no perder algo sin darte cuenta.
