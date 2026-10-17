---
title: Pacientes
sort: 20
---
# Pacientes

Fichas de pacientes del negocio. En modo humano el paciente es la persona; en modo animal es el animal y su **tutor** es un cliente de [Reservas](office-clientes).

**Ruta:** `Historia clínica → Pacientes` (`/backend/aero/medical/patients`)
**Controlador:** `Aero\Medical\Controllers\Patients`
**Modelo:** `Aero\Medical\Models\Patient`
**Permiso:** `aero.medical.use`, `.reception` o `.professional`

## Datos

| Campo | Modo | Nota |
|-------|------|------|
| Nombre, documento, nacimiento (o edad aproximada), sexo | ambos | |
| Grupo sanguíneo, antecedentes familiares | humano | solo personal clínico |
| Especie, raza, esterilizado, color y señas, microchip, peso | animal | |
| Alergias, medicación actual, antecedentes, observaciones | ambos | **solo personal clínico**, se guardan cifrados |
| Cliente de Reservas, o nombre y teléfono/correo del tutor | ambos | Los datos de contacto no se duplican: salen del cliente. Si no existe, se crea (o se reutiliza si el teléfono o correo ya existen). |

> [!IMPORTANT]
> En modo animal, todo paciente necesita su tutor. Un tutor puede tener varios animales.

> [!NOTE]
> Recepción ve y edita los datos administrativos; no ve alergias, medicación, antecedentes ni nada clínico.

La ficha del paciente (para personal clínico) reúne alergias, hoja de seguimiento, notas, vacunas, archivos y documentos en una pantalla: ver [Hoja de seguimiento](medical-hoja-de-seguimiento).

**Versión documentada:** 1.12.0
