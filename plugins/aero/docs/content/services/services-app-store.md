---
title: App Store (comprar servicios)
sort: 10
---
# App Store (comprar servicios)

**Ruta:** `Panel → App Store` (menú inferior, junto a Wallet)  
**Controlador:** `Aero\Services\Controllers\MyServices`  
**Permiso:** Ninguno (visible para todos los usuarios del panel que tengan un sitio activo)

El App Store es el catálogo de servicios que el equipo de la plataforma ofrece (por ejemplo, tienda en línea o marketing). Compras un plan con tus monedas o con tu saldo en Bs, y el equipo lo entrega después. Siempre compras para **tu propio sitio**.

> [!NOTE]
> Si ves el aviso «Elige un sitio», selecciona primero tu sitio (tenant). Sin sitio no se pueden ver ni comprar servicios.

## Qué ves en la pantalla

### Tus saldos

En la parte superior aparece una tarjeta por cada tipo de moneda activa con tu saldo, y otra con tu **Saldo en Bs**. Si te falta saldo, recárgalo desde [Wallet](credits-wallet).

### Pestaña Catálogo

Muestra cada servicio disponible, con su categoría, un resumen y una tarjeta por cada **plan**. Solo aparecen los servicios activos que tienen al menos un plan.

Cada plan muestra:

| Dato | Descripción |
|------|-------------|
| **Nombre** | Nombre del plan |
| **Precio** | Una o más líneas con el precio en créditos y/o en Bs, según cómo esté configurado el plan |
| **Entrega** | «Entrega en N días», si el plan lo indica |
| **Botones de compra** | Depende de la modalidad de cobro del plan (ver abajo) |

### Pestaña Mis compras

Lista tus últimas 50 compras con fecha, servicio, plan, monto pagado y estado:

| Estado | Significado |
|--------|-------------|
| **Pendiente** | Cobrada, a la espera de que el equipo la entregue |
| **Entregada** | El equipo ya entregó el servicio |
| **Cancelada** | La compra fue cancelada y reembolsada |

## Cómo comprar

1. Entra a **App Store** y revisa la pestaña **Catálogo**.
2. En el plan que te interesa, pulsa el botón de compra que corresponda.
3. Confirma en el aviso que aparece.
4. Verás el mensaje «Compra registrada». La compra queda **Pendiente** hasta que el equipo la entregue.

Los botones dependen de la modalidad del plan:

| Botón | Cuándo aparece | Qué hace |
|-------|----------------|----------|
| **Solicitar gratis** | Plan gratuito | Registra la solicitud sin cobrar |
| **Comprar con créditos** | El plan tiene precio en créditos | Descuenta las monedas del color del plan |
| **Comprar con mi saldo en Bs** | El plan tiene precio en Bs | Descuenta de tu saldo en Bs |
| **A cotizar: escríbenos.** | El plan está «a cotizar» (sin precio) | No se puede comprar; contacta al equipo para cerrar el precio |

> [!IMPORTANT]
> Pagar con saldo en Bs **no genera un QR nuevo**: si tu saldo no alcanza, la compra falla y debes recargar antes en [Wallet](credits-wallet). Con créditos pasa lo mismo si no tienes suficientes monedas del color del plan.

> [!NOTE]
> Si el plan tiene cargo de instalación, el pago en Bs suma el precio y ese cargo.

## Cancelar una compra

Mientras una compra esté **Pendiente**, puedes cancelarla desde **Mis compras** con el enlace **Cancelar**. Se te **reembolsa de inmediato** lo que pagaste. Una compra ya entregada o cancelada no se puede cancelar.

## Consejos

> [!TIP]
> Revisa el tiempo de entrega y la modalidad de cobro antes de comprar. Si dudas entre pagar con créditos o con saldo en Bs, mira tus saldos en las tarjetas de arriba.

**Versión documentada:** 1.11.0
