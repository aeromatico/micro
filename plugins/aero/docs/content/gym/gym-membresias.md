---
title: Planes y membresías
sort: 20
---
# Formularios: Plan y Membresía

**Ruta:** `Gimnasio → Planes` y `Gimnasio → Membresías`
**Controladores:** `Aero\Gym\Controllers\Plans`, `Aero\Gym\Controllers\Memberships`
**Modelos:** `Aero\Gym\Models\Plan`, `Aero\Gym\Models\Membership`
**Permiso:** `aero.gym.use`

El **plan** es lo que vendes (mensual, trimestral, pase de clases…). La **membresía** es el plan contratado por un socio, con fecha de inicio y vencimiento.

## Plan

| Campo | Para qué sirve |
|-------|----------------|
| **Nombre** | Obligatorio. |
| **Activo** | Los planes inactivos no se pueden contratar. |
| **Precio** | Precio del plan. |
| **Duración (días)** | Días de vigencia desde el inicio. |
| **Clases por semana** | Vacío = ilimitadas. |
| **Producto de la tienda** | ID opcional del producto en Shop. |
| **Descripción** | Texto de apoyo. |

## Membresía

| Campo | Para qué sirve |
|-------|----------------|
| **Socio** y **Plan** | Obligatorios. Si dejas precio y fecha de fin vacíos, se completan con los del plan. |
| **Inicia** / **Vence** | Rango de vigencia. |
| **Precio** y **Moneda** | Lo que se cobra. |
| **Estado** | `Pendiente de pago`, `Activa`, `Vencida` o `Cancelada`. |
| **Referencia de pago** | Referencia del cobro por QR. |
| **Motivo de cancelación** | Se guarda al cancelar. |

## Cobro por QR

Si en [Configuración](gym-configuracion) elegiste una **cuenta de Bolivia Pay**, al crear la membresía se genera un QR bancario. Cuando el pago se confirma, la membresía pasa a **Activa** sola. Sin cuenta, el cobro es manual.

> [!NOTE]
> Una membresía vencida deja de dar acceso al terminar los **días de gracia** configurados.

**Versión documentada:** 1.3.0
