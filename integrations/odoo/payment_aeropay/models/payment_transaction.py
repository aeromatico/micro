import logging

from werkzeug.urls import url_join

from odoo import _, fields, models
from odoo.exceptions import ValidationError
from odoo.addons.payment import utils as payment_utils

_logger = logging.getLogger(__name__)

# Estados de un QR en Aero.Pay -> acción sobre la transacción de Odoo.
STATUS_MAPPING = {
    'pending': ('pending',),
    'paid': ('paid',),
    'cancelled': ('cancelled',),
    'expired': ('expired',),
}


class PaymentTransaction(models.Model):
    _inherit = 'payment.transaction'

    aeropay_qr_image = fields.Text(string="Imagen del QR (base64)", copy=False)
    aeropay_redirect_url = fields.Char(string="URL de pago", copy=False)

    # ------------------------------------------------------------------ #
    # Emisión del cobro                                                  #
    # ------------------------------------------------------------------ #

    def _get_specific_rendering_values(self, processing_values):
        res = super()._get_specific_rendering_values(processing_values)
        if self.provider_code != 'aeropay':
            return res

        if not self.provider_reference:  # reintento: se reutiliza el QR ya emitido
            self._aeropay_create_qr()

        base_url = self.provider_id.get_base_url()
        return {
            'api_url': url_join(base_url, '/payment/aeropay/pay'),
            'reference': self.reference,
            'access_token': payment_utils.generate_access_token(self.reference, self.amount),
        }

    def _aeropay_create_qr(self):
        self.ensure_one()
        provider = self.provider_id
        base_url = provider.get_base_url()
        payload = {
            'amount': self.amount,
            'currency': self.currency_id.name,
            'description': self.reference,
            'external_reference': self.reference,
            'single_use': True,
            'return_url': url_join(base_url, '/payment/status'),
            'cancel_url': url_join(base_url, '/payment/status'),
        }
        if provider.aeropay_bank_account_id:
            payload['bank_account_id'] = provider.aeropay_bank_account_id
        if self.partner_email:
            payload['payer_email'] = self.partner_email

        data = provider._aeropay_request('POST', 'qr', payload=payload).get('data') or {}

        # Si Aero.Pay agrega impuestos al monto, el QR no coincide con el pedido.
        if abs(float(data.get('amount', 0)) - self.amount) > 0.01:
            raise ValidationError(_(
                "Market Pay: el QR salió por %(qr)s y el pedido es %(order)s. "
                "Desactiva el impuesto adicional en la configuración de Pay.",
                qr=data.get('amount'), order=self.amount,
            ))

        self.write({
            'provider_reference': str(data['id']),
            'aeropay_qr_image': data.get('qr_image'),
            'aeropay_redirect_url': data.get('redirect_url'),
        })

    # ------------------------------------------------------------------ #
    # Notificaciones (webhook y sondeo)                                  #
    # ------------------------------------------------------------------ #

    def _get_tx_from_notification_data(self, provider_code, notification_data):
        tx = super()._get_tx_from_notification_data(provider_code, notification_data)
        if provider_code != 'aeropay' or len(tx) == 1:
            return tx

        qr_id = notification_data.get('id')
        tx = self.search([('provider_reference', '=', str(qr_id)), ('provider_code', '=', 'aeropay')])
        if not tx:
            raise ValidationError(_("Market Pay: no hay transacción para el QR %s.", qr_id))
        return tx

    def _process_notification_data(self, notification_data):
        super()._process_notification_data(notification_data)
        if self.provider_code != 'aeropay':
            return

        status = notification_data.get('status')
        if status not in STATUS_MAPPING:
            _logger.warning("Aero.Pay: estado desconocido %r en la transacción %s", status, self.reference)
            self._set_error(_("Market Pay: estado desconocido '%s'.", status))
        elif status == 'pending':
            if self.state == 'draft':
                self._set_pending()
        elif self.state in ('draft', 'pending'):
            if status == 'paid':
                self._set_done()
            elif status == 'cancelled':
                self._set_canceled(_("El cobro fue anulado en Market Pay."))
            else:
                self._set_canceled(_("El QR de pago venció."))

    def _aeropay_sync(self):
        """Consulta el estado real del QR en Aero.Pay (fuente de verdad) y concilia."""
        self.ensure_one()
        if self.provider_code != 'aeropay' or not self.provider_reference:
            return
        data = self.provider_id._aeropay_request('GET', f'qr/{self.provider_reference}').get('data') or {}
        if data.get('status') == 'paid' and abs(float(data.get('amount', 0)) - self.amount) > 0.01:
            self._set_error(_("Market Pay: el monto pagado no coincide con el pedido."))
            return
        self._handle_notification_data('aeropay', data)
