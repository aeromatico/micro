# Formulario: Categorías

**Ruta:** `Docs → Categorías`
**Controlador:** `Aero\Docs\Controllers\Categories`
**Modelo:** `Aero\Docs\Models\Category`
**Permiso:** `aero.docs.manage`

Organiza el contenido en un **árbol de categorías multinivel** (una categoría
puede ser hija de otra). Es la estructura de navegación del centro de ayuda.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Nombre visible. |
| **Slug** | requerido, `alpha_dash` | URL de la categoría. Se propone desde el nombre. |
| **Categoría padre** | — | Déjalo vacío para primer nivel; elige una para anidar. |
| **Icono** | — | Un emoji o símbolo corto (📦, 🚀, ⚙️). |
| **Descripción** | — | Texto que resume la categoría. |
| **Activa** | — | Si está apagada, no se muestra. |
| **Global** | — | Se muestra en el centro de ayuda de **todos** los sitios. |

## Cómo se organiza

```text
Primeros pasos
├── Tu cuenta
└── Configuración
Guías
├── Ventas
└── Soporte
```

- Una categoría con su **padre** vacío es de primer nivel.
- El slug es único **dentro de tu sitio** (cada sitio puede repetir nombres).
- Al eliminar una categoría, sus artículos quedan sin categoría.

> [!TIP]
> Piensa el árbol antes de cargar contenido: dos o tres niveles suelen ser
> suficientes. Si necesitas mover ramas, usa [Ordenar árbol](docs-ordenar-arbol).
