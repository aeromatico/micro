---
title: Mis atajos
sort: 10
---
# Formulario: Mis atajos

**Ruta:** `Atajos → Mis atajos`
**Controlador:** `Aero\Shortcuts\Controllers\Items`
**Modelo:** `Aero\Shortcuts\Models\Item`
**Permiso:** `aero.shortcuts.use`

Cada registro es un botón de tu dashboard.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre del botón** | requerido, máx. 60 caracteres | Texto que verás en el botón. |
| **Tipo** | requerido | **Lista** (abre la pantalla), **Formulario (nuevo registro)** (abre «Nuevo» de esa pantalla) o **URL personalizada**. Por defecto, Lista. |
| **Pantalla** | requerido si el tipo es Lista o Formulario | Pantalla del menú a la que apuntas, con buscador. Solo aparecen las que tu usuario puede ver. |
| **URL** | requerido si el tipo es URL; máx. 500 | Dirección completa `https://…` o ruta relativa que empiece con `/` (ej. `/backend/aero/hello/compose`). No se admite `//host` ni `javascript:`. |
| **Abrir en pestaña nueva** | solo con tipo URL | Activado por defecto. Apágalo para abrir la URL en la misma pestaña. |
| **Ícono** | — | Uno de la lista (estrella, correo, chat, calendario, tienda, pagos…). Por defecto, estrella. |
| **Color** | — | Uno de ocho colores predefinidos. |
| **Visible en el dashboard** | — | Apágalo para ocultar el botón sin borrarlo. |

## Comportamiento

- Los campos **Pantalla**, **URL** y **Abrir en pestaña nueva** aparecen según el **Tipo** elegido.
- Un botón nuevo se coloca al final de tu fila; puedes arrastrarlo para reordenarlo.
- Los tipos Lista y Formulario siempre abren en la misma pestaña; solo las URL pueden abrirse en una nueva.

> [!TIP]
> Usa el tipo **Formulario** para las tareas que repites (nuevo contacto, nuevo mensaje) y llegar directo a «Nuevo».

Relacionado: [Vista general](shortcuts-vista-general)

**Versión documentada:** 1.1.0
