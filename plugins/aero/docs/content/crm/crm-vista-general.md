# CRM — Vista general

El **CRM** es el centro de clientes de tu sitio: guarda empresas y contactos,
organiza listas, da seguimiento a leads y negocios (pipeline), agenda
actividades, gestiona cobranzas y atiende tickets de soporte.

Todo funciona por sitio (tenant): tus datos están aislados de los de otros
negocios. El módulo se **activa por sitio** desde su configuración; al
activarlo se crea un pipeline de ventas por defecto.

## Menú

| Sección | Para qué sirve |
|---------|----------------|
| **Empresas** | Organizaciones con las que te relacionas. |
| **Contactos** | Personas, con su origen y empresa. |
| **Listas** | Agrupaciones de contactos (campañas, cobranzas). |
| **Cobranzas** | Cobros pendientes y recordatorios. |
| **Leads** | Prospectos antes de ser oportunidad. |
| **Pipeline** | Tablero kanban de negocios por etapa. |
| **Actividades** | Llamadas, reuniones, tareas y notas. |
| **Respuestas** | Respuestas rápidas para el chat. |
| **Equipo** | Personas con acceso a administrar el sitio. |
| **Tickets** | Mesa de ayuda al cliente. |
| **Departamentos** | Áreas que atienden tickets. |
| **Configuración de CRM** | Activar el módulo y ajustar cobranzas. |

## Flujo comercial típico

```text
Lead  →  Contacto/Empresa  →  Negocio en el Pipeline  →  Ganado
                                      │
                                      └── Actividades (llamadas, tareas…)
```

En paralelo: **Cobranzas** persigue los pagos y **Tickets** atiende el soporte.

## Guías de cada función

- [Configuración de CRM](crm-configuracion) — activar el módulo y cobranzas.
- [Empresas](crm-empresas) — el registro de organizaciones.
- [Contactos](crm-contactos) — el registro de personas.
- [Listas](crm-listas) — agrupar contactos.
- [Cobranzas](crm-cobranzas) — cobros y recordatorios.
- [Automatización de cobranzas](crm-automatizacion-cobranzas) — reglas de recordatorio.
- [Leads](crm-leads) — prospectos.
- [Pipeline](crm-pipeline) — negocios por etapa.
- [Actividades](crm-actividades) — seguimiento.
- [Respuestas rápidas](crm-respuestas) — atajos para el chat.
- [Equipo](crm-equipo) — accesos al sitio.
- [Tickets](crm-tickets) — soporte.
- [Departamentos](crm-departamentos) — áreas de soporte.

## Permisos

| Permiso | Desbloquea |
|---------|-----------|
| `aero.crm.manage_companies` | Empresas |
| `aero.crm.manage_contacts` | Contactos y Listas |
| `aero.crm.manage_leads` | Leads |
| `aero.crm.manage_deals` | Pipeline |
| `aero.crm.manage_activities` | Actividades |
| `aero.crm.manage_collections` | Cobranzas |
| `aero.crm.manage_teams` | Equipo |
| `aero.crm.manage_tickets` | Tickets |
| `aero.crm.manage_departments` | Departamentos |
| `aero.crm.manage_quick_replies` | Respuestas rápidas |
| `aero.crm.manage_settings` | Configuración de CRM |
