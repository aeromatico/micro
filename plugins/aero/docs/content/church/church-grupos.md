---
title: Ministerios, células y grupos
sort: 20
---
# Ministerios, células y grupos

**Ruta:** `Iglesia → Grupos` y `Reuniones`
**Controladores:** `Groups`, `Meetings`
**Modelos:** `Group`, `GroupMember`, `Meeting`, `Attendance`
**Permiso:** `aero.church.use`, `pastor`, `secretary`, `leader`

## Grupos

Un grupo puede ser un **ministerio**, una **célula**, un grupo de **jóvenes**, de **niños** u otro. Cada uno define sede, líder (un miembro), día, hora y lugar de reunión.

| Campo | Descripción |
|-------|-------------|
| **Líder (miembro)** | Persona que lidera el grupo. |
| **Responsable en el panel** | Usuario del panel que administrará SOLO este grupo (rol *líder de ministerio*). |
| **Día, hora y lugar** | Cuándo y dónde se reúne. |

En la ficha del grupo, el panel **Integrantes** agrega miembros con rol *líder*, *ayudante* o *integrante*. **Quitar** a alguien lo da de baja con fecha: no se borra su historial.

## Reuniones y asistencia

Cada reunión pertenece a un grupo y tiene fecha, hora, lugar y notas. En su ficha, la lista de integrantes vigentes permite marcar quién asistió y **Guardar asistencia**.

> [!IMPORTANT]
> Un líder solo ve y administra los grupos donde es el responsable del panel. Crear grupos nuevos es de la administración.

## Reportes

El reporte *Participación por ministerio y grupo* muestra integrantes, nuevos en el periodo, reuniones y asistencia promedio. Ver [Reportes](church-reportes-auditoria).

**Versión documentada:** 1.6.0
