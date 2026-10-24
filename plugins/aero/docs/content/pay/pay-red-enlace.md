---
title: Red Enlace (ATC)
sort: 25
featured: false
---
# Red Enlace (ATC)

**Red Enlace** (ATC, Administradora de Tarjetas de Crédito S.A.) es el partner de Market y la pasarela que recomendamos para cobrar en Bolivia. Con una sola afiliación puedes cobrar con cinco tipos de QR y recibir el dinero directo en tu cuenta bancaria. Market no procesa ni retiene fondos: se conecta a la API oficial de Red Enlace para generar el cobro, confirmar el pago y ordenar tus procesos comerciales.

## Qué puedes cobrar

| Método | Qué permite |
|--------|-------------|
| **QR Simple** | QR interbancario: paga cualquier cliente desde la app de su banco. Es el único que permite anular el QR. |
| **QR MLD / BCB** | Otro QR de cobro habilitado por Red Enlace. |
| **QR Binance** | El cliente paga con Binance Pay (en USDT) y tú recibes bolivianos. |
| **QR Activos Virtuales** | Cobro en USDT o USDC; tú recibes bolivianos. |
| **QR PIX** | Clientes de Brasil pagan con PIX en reales y tú recibes en bolivianos. |

Las vigencias y el detalle de cada proveedor están en [Cuentas bancarias](pay-cuentas-bancarias).

## Paso 1: afiliarte a Red Enlace

Antes de conectar la cuenta en Market, tu negocio debe estar afiliado en Red Enlace. En la página del servicio **Bolivia Pay**, el botón **Ver requisitos y afiliarme ahora** te pide elegir tu tipo de comerciante, te muestra los requisitos y abre un WhatsApp con Red Enlace con el mensaje ya escrito. También puedes afiliarte en línea desde el sitio de Red Enlace.

| Tipo de comerciante | Requisitos |
|---------------------|------------|
| **Persona natural con NIT** | Carnet de identidad vigente · NIT o Certificado de Inscripción (régimen simplificado o general) · Cuenta bancaria en el banco de tu preferencia |
| **Persona natural sin NIT** | Carnet de identidad vigente · Cuenta bancaria en el banco de tu preferencia |
| **Persona jurídica (S.R.L., S.A.), con o sin NIT** | NIT o Certificado de Inscripción (si no cuentas con NIT, se omite) · Carnet de identidad vigente del representante legal · Cuenta bancaria · Testimonio de constitución · Poder del representante legal |

> [!NOTE]
> SEPREC no es requisito de afiliación, pero Red Enlace revisa legalmente ese documento y el comercio debe tenerlo al día.

### Rubros que pueden afiliarse sin NIT

- **Comerciantes minoristas:** compra y venta de mercaderías o servicios en mercados públicos, ferias, kioskos, tiendas de barrio y puestos en vía pública.
- **Vivanderos:** venta de comidas (food trucks), bebidas y alimentos en kioskos y pequeños locales.
- **Artesanos:** quienes ejercen un arte u oficio manual por cuenta propia, en su taller o a domicilio.
- **Transporte público de menor escala:** propietarios de hasta dos vehículos de transporte público urbano, interprovincial o de carga en un mismo distrito. No incluye flotas de transporte internacional o interdepartamental ni empresas de transporte urbano que tributan según la Ley Nº 843.

### Atención

- WhatsApp de afiliación: de 9:00 a 13:00 y de 14:00 a 17:00.
- Líneas gratuitas 24/7: **Comercios 800 10 3366** · **Usuarios 800 10 3060**.

## Paso 2: conectar tu cuenta en Market

Cuando Red Enlace te habilite el acceso a su API (OpenHub), crea la cuenta en **Bolivia Pay → Cuentas → Nueva cuenta** y elige como **Banco** el producto que quieres cobrar. Necesitas:

| Dato | Qué es |
|------|--------|
| **client_id** y **client_secret** | Credenciales OAuth 2.0 que te entrega Red Enlace. |
| **base_url** | El ambiente que te asignan (sandbox o producción). |
| **id_establecimiento** y **nombre_establecimiento** | Los datos de tu comercio registrados en Red Enlace. |
| **Activo virtual** (solo QR Activos Virtuales) | `UT` = USDT, `UP` = USDC, `BK`. |

Pulsa **Probar conexión** para confirmar que Red Enlace acepta las credenciales. Empieza en sandbox y pasa a producción cuando te entreguen el ambiente real.

> [!IMPORTANT]
> En **QR Activos Virtuales** el activo es una propiedad de la cuenta, no del cobro. Si quieres ofrecer USDT y USDC, crea **dos cuentas** con las mismas credenciales y distinto activo virtual.

## Cómo se confirma el pago

- Red Enlace avisa a Market por **webhook** apenas el cliente paga, y el cobro pasa a *Pagado* en [Transacciones](pay-transacciones).
- Si necesitas, también se puede consultar el estado del cobro por su número de referencia.
- Tu propio sistema recibe el aviso por el webhook saliente de Market (ver [Tokens de API](pay-tokens-api)).
- Puedes configurar el encabezado y el secreto del webhook entrante en la cuenta (por defecto, el encabezado es `x-api-key`).

> [!TIP]
> Los QR de Red Enlace duran poco (de 30 segundos a unos minutos, según el producto). Úsalos para cobrar frente al cliente o en el checkout, no para dejar el cobro pendiente por horas.

## Otras opciones de cobro

Red Enlace es la opción recomendada, pero no la única: Bolivia Pay también se conecta a la API de tu banco (BNB, Banco Económico y otros), al QR fijo detectado por correo, a PayPal y a NOWPayments. Ver [Vista general](pay-vista-general).

**Versión documentada:** 1.28.0
