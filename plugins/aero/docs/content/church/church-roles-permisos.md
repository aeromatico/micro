---
title: Roles y permisos de la iglesia
sort: 5
---
# Roles y permisos de la iglesia

**Ruta:** *Ajustes → Usuarios y roles* (o el panel de usuarios de tu iglesia)
**Permisos:** `aero.church.*`

El administrador general de la iglesia tiene acceso a todo. Para el resto del equipo existen estos roles:

| Rol | Qué puede hacer | Qué NO ve |
|-----|-----------------|-----------|
| **Administrador general** (`aero.church.use`) | Todo: miembros, grupos, eventos, aportes, visitantes, oración, comunicados, contenido, reportes, auditoría y configuración. | — |
| **Pastor o dirección** (`church_pastor`) | Consultar miembros, resumen, reportes, **resumen de aportes sin nombres**, oración y auditoría. | La lista de aportes con aportantes. |
| **Secretaría** (`church_secretary`) | Miembros, familias, visitantes, eventos, comunicados, contenido del sitio y reportes de personas. | Aportes, oración y auditoría. |
| **Tesorería** (`church_treasury`) | Aportes, conceptos, comprobantes, resumen y detalle de aportes, y configuración de aportes. | Miembros, grupos y oración. |
| **Líder de ministerio** (`church_leader`) | Solo los grupos de los que es responsable, sus reuniones y la asistencia; y las peticiones de oración que se le asignen. | Todo lo demás. |

> [!TIP]
> Un líder solo ve los grupos donde figura como **Responsable en el panel**. Lo asigna la administración al editar el grupo.

> [!WARNING]
> Información especialmente protegida: las peticiones de oración, los aportes con nombre y los datos de menores solo los ven los roles indicados arriba.

**Versión documentada:** 1.6.0
