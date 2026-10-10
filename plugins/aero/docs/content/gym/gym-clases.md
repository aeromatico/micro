---
title: Clases y reservas
sort: 40
---
# Formularios: Tipo de clase, Horario y Clase

**Ruta:** `Gimnasio → Tipos de clase`, `Horarios`, `Clases` y `Clases en vivo`
**Controladores:** `ClassTypes`, `Schedules`, `Sessions`, `LiveBoard` (`Aero\Gym\Controllers`)
**Modelos:** `ClassType`, `Schedule`, `ClassSession`, `Booking` (`Aero\Gym\Models`)
**Permiso:** `aero.gym.use`

Las clases se organizan en tres niveles: el **tipo** (Spinning, Yoga…), el **horario semanal** (lunes 18:30) y las **clases** concretas que se generan solas a partir del horario.

## Tipo de clase

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre** | Obligatorio. |
| **Duración (min)** | Duración por defecto. |
| **Cupo por defecto** | Cupos disponibles si el horario no define otro. |
| **Color** | Identifica la clase en la agenda. |
| **Página que la explica** | Página de Sitios que presenta y promociona la clase. |
| **Descripción corta** | Texto breve. |

## Horario semanal

| Campo | Para qué sirve |
|-------|----------------|
| **Clase** | Obligatorio. |
| **Instructor** | Quién la dicta. |
| **Día de la semana** y **Hora** | Formato 24 h (`18:30`). |
| **Cupo** | Vacío = el de la clase. |
| **Sala** | Lugar donde se dicta. |
| **Activo** | Pausa el horario sin borrarlo. |

Las clases concretas se **generan cada día** a partir de los horarios activos. También puedes crear una clase suelta en **Clases**.

## Reservas y lista de espera

- El socio reserva desde su portal mientras haya cupo.
- Si la clase está llena, entra en **lista de espera**.
- Cuando alguien cancela, el primero de la lista **sube automáticamente** (si lo dejas activo) y recibe un aviso por WhatsApp.
- El socio puede cancelar hasta **N horas antes** (configurable).
- Si cancelas una clase, los inscritos reciben el aviso.

## Clases en vivo

La pantalla **Clases en vivo** muestra las clases del día (en curso y próximas) con sus inscritos, la lista de espera y los cupos, y se actualiza sola cada pocos segundos; sirve para un televisor en recepción.

**Versión documentada:** 1.3.0
