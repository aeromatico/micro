---
title: Gimnasio — vista general
sort: 0
featured: true
---
# Gimnasio — vista general

El módulo **Gimnasio** ordena la operación diaria de un gimnasio, box o estudio: quién es socio, hasta cuándo tiene pagada su membresía, quién puede entrar, qué clases hay y cuántos cupos quedan.

**Ruta:** `Gimnasio` (menú lateral)
**Permiso:** `aero.gym.use`

## Qué incluye

| Área | Para qué sirve | Artículo |
|------|----------------|----------|
| **Socios** | Ficha, carnet con QR y grupos. | [Socios](gym-socios) |
| **Planes y membresías** | Precios, duración, vencimiento y cobro por QR. | [Planes y membresías](gym-membresias) |
| **Acceso** | Validar el ingreso por QR, carnet o documento. | [Control de acceso](gym-acceso) |
| **Clases** | Tipos de clase, horarios semanales y sesiones con cupo y lista de espera. | [Clases y reservas](gym-clases) |
| **Rutinas y medidas** | Plan de ejercicios por socio y evolución corporal. | [Rutinas](gym-rutinas) · [Medidas](gym-medidas) |
| **Instructores y grupos** | Quién dicta cada clase y segmentos de socios. | [Instructores y grupos](gym-instructores-grupos) |
| **Reportes** | Asistencia, clases más demandadas, ocupación y renovaciones vs. bajas. | [Reportes](gym-reportes) |
| **Configuración** | Interruptor general, modo de acceso, recordatorios y cobro. | [Configuración](gym-configuracion) |

## Se conecta con el resto de tu plataforma

Todas las conexiones son opcionales: el gimnasio funciona solo y se enriquece con lo que ya tengas activo.

- **Bolivia Pay:** la membresía se cobra con un QR bancario y se activa sola al pagarse.
- **Hello (WhatsApp):** recordatorios de vencimiento y avisos de clases.
- **Shop:** vende suplementos y accesorios con el mismo catálogo del negocio.
- **Finanzas:** cada membresía pagada se registra como ingreso.
- **Sitios:** el socio entra a su portal `/gym` desde el micrositio del gimnasio y cada clase puede tener su página de presentación.

> [!NOTE]
> Con el **interruptor general** apagado, el gimnasio deja de validar accesos, aceptar reservas y enviar recordatorios; los datos se conservan. Ver [Configuración](gym-configuracion).

**Versión documentada:** 1.3.0
