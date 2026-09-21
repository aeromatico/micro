# Función: Equipo

**Ruta:** `CRM → Equipo`
**Controlador:** `Aero\Crm\Controllers\Team`
**Permiso:** `aero.crm.manage_teams`

Gestiona **quién puede administrar tu sitio**. Aquí invitas personas, defines
su rol y las asignas a departamentos. No confundir con el *Equipo* que se usa
para asignar negocios del pipeline: esta pantalla controla el **acceso al propio
sitio**.

## Listado

Muestra cada miembro con:

| Columna | Para qué sirve |
|---------|----------------|
| **Correo** | Cuenta de la persona. |
| **Nombre** | Nombre del usuario. |
| **Departamentos** | Áreas de soporte a las que pertenece. |
| **Rol** | Admin, Moderador o Usuario. |
| **Desde** | Cuándo se le dio acceso. |

## Invitar a alguien

El botón **Invitar** abre un formulario con:

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Correo electrónico** | requerido, email | A quién invitas. |
| **WhatsApp (opcional)** | — | Contacto adicional. |
| **Rol** | requerido | Admin, Moderador o Usuario. |

Cómo se resuelve la invitación:

- Si el correo **ya tiene cuenta** (por ejemplo, es dueño de otro sitio), se
  agrega **directo** como colaborador.
- Si **no tiene cuenta**, se le crea y se le manda un **enlace de acceso** para
  activarla.

## Departamentos por miembro

Desde la columna **Departamentos** puedes marcar a qué áreas pertenece cada
persona. Esto determina los tickets que verá y a los que puede asignarse.

> [!WARNING]
> El usuario **propietario** del sitio (el creador) está protegido: no se puede
> quitar desde el equipo.

> [!TIP]
> Da el rol **Admin** solo a quienes deban gestionar todo el sitio; para el
> trabajo diario, un **Moderador** o **Usuario** con los departamentos correctos
> es suficiente.
