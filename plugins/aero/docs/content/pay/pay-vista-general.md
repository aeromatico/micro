# Bolivia Pay — Vista general

**Bolivia Pay** es la pasarela de pagos de tu sitio. Reúne en un solo panel
varios medios de cobro: los **QR bancarios bolivianos** (BNB, Banco
Económico), el **QR estático que tú ya tienes** (confirmado por correo),
**PayPal** y **NOWPayments** (cripto).

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
1. Conectas una cuenta de banco (BNB / Banco Económico)
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
| **QR dinámico** | Un QR por transacción, con monto fijo. Bancos con API (BNB, Económico). |
| **QR estático** | El QR fijo de tu comercio: se coloca y los pagos se detectan por correo. |
| **Pasarela** | PayPal y NOWPayments: el cliente paga en el sitio del proveedor y vuelve. |
| **Manual** | Registrar a mano una transferencia que no se detectó automáticamente. |

## Guías de cada función

- [Cuentas bancarias](pay-cuentas-bancarias) — conectar bancos, correo, PayPal y cripto.
- [Sucursales](pay-sucursales) — identificar cada punto de cobro.
- [Generar código QR](pay-generar-qr) — emitir un cobro.
- [Detalle del QR](pay-detalle-qr) — estado, imagen y pagos recibidos.
- [Transacciones](pay-transacciones) — historial, totales y carga manual.
- [Tokens de API](pay-tokens-api) — integración con tus sistemas y webhooks.
- [Configuración de pagos QR](pay-configuracion) — valores por defecto.

## Permisos que habilitan este panel

| Permiso | Desbloquea |
|---------|-----------|
| `aero.pay.manage_accounts` | Cuentas, sucursales y tokens |
| `aero.pay.view_qr` | Generar/ver QR y transacciones |
| `aero.pay.manage_settings` | Configuración de pagos |
