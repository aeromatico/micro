# Bolivia Pay — Vista general

**Bolivia Pay** es la pasarela de pagos de tu sitio. Reúne en un solo panel
varios medios de cobro: los cinco QR de **Red Enlace (ATC)**, nuestro partner
recomendado (QR Simple, QR MLD/BCB, Binance Pay, Activos Virtuales USDT/USDC y
PIX de Brasil), los **QR bancarios bolivianos** (BNB, Banco Económico), el
**QR estático que tú ya tienes** (confirmado por correo), **PayPal** y
**NOWPayments** (cripto).

> [!TIP]
> ¿Quieres cobrar con Red Enlace? Mira [Red Enlace (ATC)](pay-red-enlace): requisitos de
> afiliación por tipo de comerciante, cómo conectar la cuenta y cómo se confirma el pago.

Todo funciona por sitio (tenant): tus cuentas, tus sucursales, tus QR y tus
transacciones están aislados de los de otros negocios.

## Menú

| Sección | Para qué sirve |
|---------|----------------|
| **Cuentas** | Conectar tus cuentas y comercios (bancos, correo, PayPal, cripto). |
| **Generar código** | Emitir un QR de cobro por una transacción concreta. |
| **Transacciones** | Ver todos los pagos recibidos y cargar pagos manuales. |
| **Sucursales** | Registrar sucursales para identificarlas en el QR. |
| **Configuración de pagos QR** | Reglas por defecto: sucursales, USD, vencimiento e impuestos. |
| **Tokens de API** | Acceso programático para integrar tus sistemas. |

## Flujo de un cobro con QR dinámico

```text
1. Conectas una cuenta (Red Enlace, BNB, Banco Económico…)
        ▼
2. Generas un QR por el monto y vencimiento que elijas
        ▼
3. El cliente escanea y paga
        ▼
4. El pago se confirma por webhook (instantáneo) o por conciliación
        ▼
5. Aparece en Transacciones y se puede notificar a tu sistema
```

## Formas de cobro

| Tipo | Cuándo usarlo |
|------|---------------|
| **QR dinámico** | Un QR por transacción, con monto fijo. Bancos con API (BNB, Económico) y los cinco QR de Red Enlace (ATC): QR Simple, QR PIX, QR MLD/BCB, Activos Virtuales y Binance. |
| **QR estático** | El QR fijo de tu comercio: se coloca y los pagos se detectan por correo. |
| **Pasarela** | PayPal y NOWPayments: el cliente paga en el sitio del proveedor y vuelve. |
| **Manual** | Registrar a mano una transferencia que no se detectó automáticamente. |

## Guías de cada función

- [Red Enlace (ATC)](pay-red-enlace) — afiliación, requisitos y los cinco QR.
- [Cuentas bancarias](pay-cuentas-bancarias) — conectar bancos, correo, PayPal y cripto.
- [Sucursales](pay-sucursales) — identificar cada punto de cobro.
- [Generar código QR](pay-generar-qr) — emitir un cobro.
- [Detalle del QR](pay-detalle-qr) — estado, imagen y pagos recibidos.
- [Transacciones](pay-transacciones) — historial, totales y carga manual.
- [Tokens de API](pay-tokens-api) — integración con tus sistemas y webhooks.
- [Configuración de pagos QR](pay-configuracion) — valores por defecto.

## Cobros desde Workflows

Si usas **Workflows**, el editor visual incluye nodos de **Cobros** para generar y seguir pagos dentro de una automatización:

| Nodo | Qué hace |
|------|----------|
| **Cobros › Generar cobro** | Emite un QR de cobro |
| **Cobros › Consultar estado** | Revisa el estado de un cobro |
| **Cobros › Anular** | Anula un cobro |
| **Cobros › Resumen de pagos recibidos** | Resume los pagos recibidos |

> [!IMPORTANT]
> Estos nodos solo usan **cuentas activas de tu propio sitio**. Los QR y pagos que crean quedan con el origen `workflows`.

## Tarifa por QR completado

Cuando un QR de tu sitio pasa a **pagado**, se descuenta de tus monedas de **Créditos** la tarifa de la acción `pay.qr_completed` (se ajusta en Créditos → Acciones; si la acción está desactivada, no se cobra).

- Los QR de la propia plataforma (alta de sitio, recargas y regalos de suscripción) no cobran tarifa.
- El pago ya ocurrió: si el saldo no alcanza, el QR queda pagado igual y solo se registra un aviso.
- Cada QR se cobra una sola vez.

> [!NOTE]
> Las notas de versión mencionan 3 monedas de bronce por defecto, pero el código actual aplica 8 y, en el plan PRO, el 1 % del monto del QR en Bs. Consulta el valor vigente en Créditos → Acciones.

## Integración con Shopify

Si usas el plugin **Shopify**, los QR creados para pedidos de tu tienda Shopify llevan el origen `shopify`. Cada vez que un QR cambia de estado, Pay avisa a Shopify para que marque el pedido como pagado.

## Permisos que habilitan este panel

| Permiso | Desbloquea |
|---------|-----------|
| `aero.pay.manage_accounts` | Cuentas, sucursales y tokens |
| `aero.pay.view_qr` | Generar/ver QR y transacciones |
| `aero.pay.manage_settings` | Configuración de pagos |

## Cobros en cripto con NOWPayments

El aviso de pago de la pasarela ya no se pierde: el cobro solo se marca *Pagado* cuando los fondos están confirmados. Para USDC, USDT o BTC puedes fijar la moneda y red de pago con el dato opcional `pay_currency` de la cuenta (por ejemplo `usdcsol`, `usdcmatic` o `usdctrc20`).

**Versión documentada:** 1.28.0
