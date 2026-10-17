---
title: Cobros de la atención
sort: 145
---
# Cobros de la atención

Registro de lo que se cobra por una atención, con aviso automático a Finanzas.

**Ruta:** `Historia clínica → Cobros` (`/backend/aero/medical/charges`) y botón **Registrar cobro** en una nota firmada
**Controlador:** `Aero\Medical\Controllers\Charges`
**Modelo:** `Aero\Medical\Models\Charge`
**Permiso:** `aero.medical.reception`, `.use`, `.settings` o `.professional`

| Campo | Qué hace |
|-------|----------|
| Paciente, concepto, monto, moneda (Bs o USD) | Datos del cobro. El concepto debe ser genérico: lo ve recepción y viaja a Finanzas. |
| Forma de cobro | **Efectivo, transferencia u otro:** queda cobrado al registrarlo. **QR de Bolivia Pay:** se emite un QR con una cuenta bancaria activa del negocio y el cobro queda pendiente hasta que el banco confirme. |

- Desde una nota firmada con reserva, el concepto y el monto se prellenan con los de la reserva.
- Un cobro pendiente se puede marcar como cobrado a mano; cualquiera se puede **anular** (si estaba cobrado, se anula también su asiento).
- Si Finanzas está instalada y el negocio lo permite, cada cobro genera un ingreso «Atención #n» **sin concepto ni datos clínicos**.

> [!WARNING]
> Cobrar con QR requiere Aero.Pay con una cuenta bancaria activa del negocio.

También hay una **API de pacientes** de solo lectura (`GET /api/v1/medical/patients`, permiso `medical.patients.read`, key del negocio) que devuelve únicamente datos administrativos.

**Versión documentada:** 1.12.0
