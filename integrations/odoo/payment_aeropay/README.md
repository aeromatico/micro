# payment_aeropay — Market Pay para Odoo 17 (e-commerce)

Proveedor de pago que cobra con el QR bancario de **Aero.Pay**. Compatible con `website_sale`.

## Instalación
1. Copiar la carpeta a los addons de Odoo, reiniciar, activar modo desarrollador › Aplicaciones › Actualizar lista › instalar «Pasarela de pago: Market Pay».
2. Facturación › Configuración › Proveedores de pago › **Market Pay (QR Bolivia)**:
   - **URL de la API**: `https://market.com.bo/api/v1/pay`
   - **API key**: key del tenant con permisos `qrbo.qr.create` y `qrbo.qr.read` (Pay › Tokens de API).
   - **ID de la cuenta de Pay**: id de la cuenta bancaria (vacío = la de producción más antigua).
   - **Secreto del webhook**: el mismo de la cuenta en Aero.Pay.
   - «Probar conexión», luego estado **Habilitado**.
3. En Aero.Pay, en la cuenta bancaria: webhook saliente = `https://TU-ODOO/payment/aeropay/webhook` y el mismo secreto.

## Flujo
Checkout → Odoo pide el QR (`POST /qr`) → página `/payment/aeropay/pay` muestra el QR →
el pago se confirma por webhook firmado (HMAC-SHA256) y, de respaldo, por sondeo cada 4 s.
Ambos caminos reconsultan el estado en la API; no se confía en el cuerpo del webhook.

## Notas
- Monedas: BOB y USD. Desactivar el impuesto adicional de Pay (el monto del QR debe igualar al del pedido; si no, se rechaza).
- Sin reembolsos ni tokenización.
