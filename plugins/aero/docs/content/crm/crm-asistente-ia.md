---
title: Asistente de IA (Silvia)
sort: 140
featured: false
---
# Asistente de IA (Silvia)

Silvia es el agente de IA de CRM disponible en **Workspaces**. Con las
herramientas del CRM puede consultar tus datos, preparar informes y gestionar
registros por ti, siempre con un plan acordado, confirmación y opción de deshacer.

> [!NOTE]
> Silvia actúa con los permisos `aero.crm.manage_*` de la persona que le habla:
> solo puede consultar o cambiar lo que esa persona puede ver y administrar.

## Qué puede consultar

- Búsqueda y consulta de contactos, empresas, leads, negocios, actividades y cobranzas.

## Informes con cifras calculadas

Las cifras las calcula el sistema, no la IA:

- Embudo de ventas.
- Origen de leads.
- Carga de actividades.
- Cobranzas por antigüedad.
- Calidad de contactos.
- Negocios estancados.
- Tickets.

## Qué puede gestionar

| Función | Alcance |
|---------|---------|
| [Contactos](crm-contactos) | Crear y editar, sin duplicar. |
| [Empresas](crm-empresas) | Crear y editar. |
| [Leads](crm-leads) | Crear y editar; **convertir** un lead (esta acción no se deshace). |
| [Pipeline](crm-pipeline) | Mover un negocio de etapa. |
| [Actividades](crm-actividades) | Crear y gestionar. |
| [Cobranzas](crm-cobranzas) | Marcar como pagadas o anuladas. |

## Cómo trabaja

1. Silvia propone un **plan** de lo que hará.
2. Tú lo apruebas (**confirmación**).
3. Ejecuta los cambios y puedes **deshacerlos**.

> [!IMPORTANT]
> La conversión de un lead no se puede deshacer.

## Lo que no hace

- No crea cobros ni reglas de recordatorio.
- No responde tickets a clientes.
- No borra registros.

**Versión documentada:** 1.11.0
