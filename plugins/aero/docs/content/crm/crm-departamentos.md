# Formulario: Departamentos

**Ruta:** `CRM → Departamentos`
**Controlador:** `Aero\Crm\Controllers\Departments`
**Modelo:** `Aero\Crm\Models\Department`
**Permiso:** `aero.crm.manage_departments`

Define las **áreas que atienden tickets** (Soporte, Ventas, Facturación…). Cada
ticket pertenece a un departamento y se asigna a uno de sus integrantes.

## Campos

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Nombre** | requerido | Nombre del área. |
| **Color** | — | Color para distinguirlo visualmente. |
| **Correo del departamento** | — | Opcional, para avisos y (a futuro) tickets por correo. |
| **Activo** | — | Los inactivos no se pueden elegir en tickets nuevos. |
| **Descripción** | — | Nota sobre su alcance. |
| **Integrantes** | — | Usuarios que atienden el área. |

## Cómo se usa

- Los **tickets** requieren un departamento y, al elegirlo, la asignación se
  limita a sus integrantes.
- Los **integrantes** también se administran desde [Equipo](crm-equipo), donde
  ves los departamentos por persona.
- Un mismo usuario puede pertenecer a varios departamentos.

> [!TIP]
> Deja **activos** solo los departamentos en uso: así el selector de tickets
> queda limpio y evitas asignaciones a áreas que ya no operan.
