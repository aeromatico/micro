---
title: Cobros rápidos por QR
sort: 20
---
# Cobros rápidos por QR

**Dónde:** panel de la conversación en la app de chat → Cobrar
**Requiere:** [Pay](pay-cuentas-bancarias) con al menos una cuenta bancaria **activa** de tu espacio, y la función PRO de Chat.

Genera un QR de cobro con el monto que indiques y se lo envía al cliente por la misma conversación. No
necesita contacto de CRM ni una cobranza previa.

## Datos del cobro

| Campo | Reglas | Para qué sirve |
|-------|--------|----------------|
| **Monto** | Obligatorio, mayor que 0,01, máximo 9.999.999 | Importe a cobrar, siempre en bolivianos (BOB). |
| **Descripción** | Obligatoria, hasta 100 caracteres | Concepto; viaja en el mensaje al cliente. |
| **Vence en** | Obligatorio, de 1 a 365 días | La app ofrece 1 día, 1 semana o 1 año. |
| **Cuenta bancaria** | Opcional | Si no eliges, se usa la preferida (la de cobranzas del CRM, si la configuraste) o la primera activa. |
| **Avisar al cliente al pagar** | Sí por defecto | Envía «¡Recibimos tu pago!» cuando el pago se confirma. |

## Qué ocurre al cobrar

1. Se **emite el QR** en el banco. Si el banco lo rechaza, no se gasta ningún mensaje.
2. Se registra el cobro en la conversación y se **envía la imagen del QR** con el concepto, el monto y la vigencia.
3. Si la conversación no tenía responsable, queda asignada a quien cobró.
4. Cuando el pago llega (aviso del banco o conciliación), el cobro pasa a **pagado**, aparece «Pago recibido» en el hilo, la conversación sube en la bandeja y, si lo pediste, se agradece al cliente.

Estados que ves en la lista de cobros de la conversación: **pendiente**, **pagado**, **vencido** y **cancelado**.

> [!IMPORTANT]
> El aviso de agradecimiento es un extra: si la ventana de 24 h de WhatsApp está cerrada o no hay créditos, el pago
> igualmente queda registrado, pero el cliente no recibe el mensaje.

> [!WARNING]
> Si el QR se generó pero el mensaje no pudo enviarse (sin créditos, cuenta sin soporte de imágenes), el cobro
> existe en Pay. Revísalo allí antes de volver a cobrar para no duplicarlo.

**Versión documentada:** 1.2.0
