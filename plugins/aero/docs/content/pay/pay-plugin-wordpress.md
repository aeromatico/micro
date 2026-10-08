# Plugin de WordPress (WooCommerce)

Si ya vendes con **WordPress + WooCommerce**, no necesitas migrar tu tienda
para cobrar con **Bolivia Pay**: instala el plugin gratuito en tu WordPress y
tu tienda actual queda conectada a tu cuenta de Pagos.

Cada pedido genera su propio **QR bancario** (BNB, Banco Económico y los
demás bancos habilitados), el cliente lo escanea desde su app y el pedido
pasa solo a **Procesando** apenas el banco confirma el pago.

> [!NOTE]
> Por ahora se descarga como archivo `.zip` desde este equipo de soporte.
> Pronto habrá una forma de descargarlo directo desde **Bolivia Pay →
> Configuración**, sin pedirlo aparte.

## Requisitos

- WordPress con **WooCommerce** instalado y activo.
- Tu tienda debe facturar en **bolivianos (BOB)** — es la única moneda que
  Bolivia Pay puede confirmar automáticamente desde fuera del panel.
- Una **API key** con los permisos `qrbo.qr.create`, `qrbo.qr.read` y
  `qrbo.qr.cancel` (ver [Tokens de API](pay-tokens-api)).
- Al menos una [cuenta bancaria](pay-cuentas-bancarias) activa en producción.

## Instalación

1. Sube la carpeta del plugin a `wp-content/plugins/` de tu WordPress (o
   instala el `.zip` desde **Plugins → Añadir nuevo → Subir plugin**) y
   actívalo.
2. En Bolivia Pay, crea una **API key** con los permisos de cobro (ver
   [Tokens de API](pay-tokens-api)) y copia el token — solo se muestra una
   vez.
3. En WordPress, ve a **WooCommerce → Ajustes → Pagos → Aero Pay (QR
   bancario)** y completa:
   - **URL de la API**: el dominio de tu sitio en Market, sin `/api` al
     final.
   - **API key**: la que creaste en el paso 2.
   - **Secreto del webhook**: inventa una cadena aleatoria — la vas a
     repetir en el siguiente paso.
   - **Cuenta bancaria** (opcional): déjalo vacío para usar tu cuenta de
     producción más antigua, o pon el ID de una en particular.
4. Copia la **URL del webhook de confirmación** que muestra esa misma
   pantalla de ajustes.
5. En Bolivia Pay, entra a la [cuenta bancaria](pay-cuentas-bancarias) que
   vas a usar y completa:
   - **Outbound webhook URL**: la URL que copiaste en el paso 4.
   - **Outbound webhook secret**: exactamente el mismo valor del paso 3.
6. Activa la pasarela en WooCommerce. Ya puedes recibir pedidos con QR.

## Cómo se ve para el cliente

- Al pagar, WooCommerce lo lleva a la pantalla de **pedido recibido** con el
  QR y el monto a pagar.
- Mientras espera, la página consulta el estado cada pocos segundos —no
  necesita recargar a mano.
- Apenas el banco confirma, Bolivia Pay avisa por **webhook** y el pedido
  pasa a **Procesando** al instante. Si el webhook aún no está configurado
  (o tarda), el sondeo desde el navegador del cliente confirma el pago igual,
  solo que unos segundos después.
- Si nadie paga antes de que el QR venza, el pedido se **cancela solo** — no
  quedan pedidos "en espera" acumulándose.

## Preguntas frecuentes

**¿Funciona con otras monedas además de BOB?**
No. El cobro automático solo puede confirmarse en bolivianos. Si tu tienda
factura en otra moneda, este método de pago no estará disponible en el
checkout.

**¿Qué pasa si no configuro el webhook (pasos 4 y 5)?**
El plugin igual funciona: el sondeo del navegador confirma el pago sin él.
El webhook solo hace la confirmación instantánea y no depende de que el
cliente deje la pestaña abierta.

**¿El dinero pasa por Market?**
No. El QR se genera contra tu propia cuenta bancaria dada de alta en
[Cuentas bancarias](pay-cuentas-bancarias) — el dinero llega directo ahí.

> [!TIP]
> Si vendes con varias cuentas bancarias (por ejemplo una por sucursal),
> puedes instalar el plugin más de una vez con distintas API keys, o fijar
> el campo **Cuenta bancaria** en los ajustes para elegir cuál usar.
