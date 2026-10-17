---
title: Configuración
sort: 10
---
# Configuración

Define el modo del negocio, las plantillas con las que se abre cada nota y los avisos de vacunas y desparasitaciones.

**Ruta:** `Historia clínica → Configuración` (`/backend/aero/medical/settings`)
**Controlador:** `Aero\Medical\Controllers\Settings`
**Modelo:** `Aero\Medical\Models\ClinicSettings`
**Permiso:** `aero.medical.settings`

## Campos

| Campo | Qué hace |
|-------|----------|
| Modo del negocio | **Humano** (personas) o **animal** (veterinaria). Fija el vocabulario (Médico/Veterinario, Persona de contacto/Tutor), los campos del paciente, los signos vitales y los módulos de animales. Se elige al empezar. |
| Confirmo el cambio de modo | Hace falta solo si ya hay pacientes. |
| Plantilla con la que abre cada Consulta / Control | Cuál de sus [plantillas](medical-plantillas-de-nota) usa cada tipo de nota. |
| Avisar al cliente las vacunas próximas | Apagado por defecto. Ver [Vacunación](medical-vacunacion). |
| Días de anticipación | Con cuántos días de adelanto se avisa (vacunas y desparasitaciones). |
| Avisar al tutor las desparasitaciones próximas | Solo animal; apagado por defecto. |
| Intervalo habitual entre desparasitaciones (días) | Opcional; si está vacío la próxima fecha se indica a mano. |
| Módulo activo | Interruptor general. |

> [!WARNING]
> Cambiar el modo **no borra nada**: cada paciente conserva el modo con que se creó (y sus campos). Solo cambian los textos y campos para los pacientes nuevos. Las plantillas ya creadas no se vuelven a sembrar: edítelas a mano.

**Versión documentada:** 1.12.0
