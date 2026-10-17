---
title: Vacunación
sort: 100
---
# Vacunación

Catálogo de vacunas con esquema, dosis aplicadas, lote, próxima dosis y recordatorio.

**Catálogo:** `Historia clínica → Vacunas` (`/backend/aero/medical/vaccines`)
**Modelos:** `Aero\Medical\Models\Vaccine`, `Vaccination`
**Permiso:** `aero.medical.professional` (catálogo: también `.settings`)

## Catálogo y esquema

Nombre, especie (animal; vacío = todas), refuerzo periódico (días) y las **dosis** en orden: la primera sin días y las demás con los días desde la dosis anterior. Lo define el negocio; el sistema no trae esquemas.

## En la ficha

- **Dosis aplicadas:** fecha, lote, dosis (la que sigue el esquema), próxima dosis (la del esquema o la que indique a mano).
- **Próximas dosis:** vencidas y pendientes, con botón **Agendar** que prellena el control.
- **Sin iniciar:** vacunas del catálogo que aplican al paciente y aún no se aplicaron.

## Recordatorios

Cada día a las 09:00 se avisa al cliente o tutor (WhatsApp o correo, por Aero.Notify) de las dosis próximas, **una vez por dosis** y solo si no se aplicó una posterior. Se activa en [Configuración](medical-configuracion) (apagado por defecto). En personas el mensaje no nombra la vacuna.

**Versión documentada:** 1.10.1
