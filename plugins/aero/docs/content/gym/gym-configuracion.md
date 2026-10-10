---
title: Configuración
sort: 90
---
# Formulario: Configuración del gimnasio

**Ruta:** `Gimnasio → Configuración`
**Controlador:** `Aero\Gym\Controllers\Settings`
**Modelo:** `Aero\Gym\Models\GymSettings`
**Permiso:** `aero.gym.use`

## Campos

| Campo | Para qué sirve |
|-------|----------------|
| **Sistema activo** | Apagado, no se valida acceso, no se aceptan reservas y no se envían recordatorios. Los datos se conservan. |
| **Modo de acceso** | QR del socio (clásico) o QR del gimnasio que escanea el socio con su móvil. |
| **El QR del gimnasio cambia cada (segundos)** | Entre 10 y 600. Más corto = más seguro frente a capturas de pantalla. |
| **Cuenta para cobrar membresías** | Cuenta de Bolivia Pay. Sin cuenta, el cobro es manual. |
| **Moneda** | Moneda de los cobros. |
| **Recordar vencimientos por WhatsApp** | Activa los recordatorios (requiere Hello). |
| **Días antes de vencer** | Separados por coma; `0` = el día del vencimiento. Por defecto `3,1,0`. |
| **Mensaje del recordatorio** | Variables: `{nombre}` `{plan}` `{cuando}` `{fecha}` `{precio}` `{moneda}`. Vacío = mensaje por defecto. |
| **Días de gracia** | Días con acceso después de vencer. |
| **Cancelar hasta (h antes)** | Límite para que el socio cancele una reserva. |
| **Subir automáticamente de la lista de espera** | Promueve al siguiente cuando se libera un cupo. |
| **Colección de la tienda** | Productos del gimnasio en Shop. |

**Versión documentada:** 1.3.0
