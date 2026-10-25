import logging

import requests
from werkzeug.urls import url_join

from odoo import _, fields, models
from odoo.exceptions import UserError, ValidationError

_logger = logging.getLogger(__name__)

SUPPORTED_CURRENCIES = ('BOB', 'USD')


class PaymentProvider(models.Model):
    _inherit = 'payment.provider'

    code = fields.Selection(
        selection_add=[('aeropay', "Market Pay (QR Bolivia)")],
        ondelete={'aeropay': 'set default'},
    )
    aeropay_api_url = fields.Char(
        string="URL de la API",
        default='https://market.com.bo/api/v1/pay',
        required_if_provider='aeropay',
        help="Base de la API de Aero.Pay, sin barra final. Ej.: https://market.com.bo/api/v1/pay",
    )
    aeropay_api_key = fields.Char(
        string="API key",
        required_if_provider='aeropay',
        groups='base.group_system',
        help="Key del tenant con los permisos qrbo.qr.create y qrbo.qr.read.",
    )
    aeropay_bank_account_id = fields.Integer(
        string="ID de la cuenta de Pay",
        help="ID de la cuenta bancaria en Aero.Pay (Pay › Cuentas). Vacío: se usa la cuenta de "
             "producción más antigua del tenant. En modo de prueba debe indicarse una cuenta sandbox.",
    )
    aeropay_webhook_secret = fields.Char(
        string="Secreto del webhook",
        required_if_provider='aeropay',
        groups='base.group_system',
        help="El mismo 'secreto del webhook saliente' configurado en la cuenta de Aero.Pay. "
             "Firma cada aviso de pago (cabecera X-Pay-Signature).",
    )
    aeropay_timeout = fields.Integer(string="Timeout (s)", default=15)

    # ------------------------------------------------------------------ #
    # Compatibilidad con el e-commerce                                   #
    # ------------------------------------------------------------------ #

    def _get_supported_currencies(self):
        supported = super()._get_supported_currencies()
        if self.code == 'aeropay':
            supported = supported.filtered(lambda c: c.name in SUPPORTED_CURRENCIES)
        return supported

    def _get_default_payment_method_codes(self):
        default_codes = super()._get_default_payment_method_codes()
        if self.code != 'aeropay':
            return default_codes
        return {'aeropay'}

    # ------------------------------------------------------------------ #
    # Cliente HTTP de Aero.Pay                                           #
    # ------------------------------------------------------------------ #

    def _aeropay_request(self, method, endpoint, payload=None, params=None):
        """Llama a la API de Aero.Pay y devuelve el JSON; ValidationError si falla."""
        self.ensure_one()
        base = (self.aeropay_api_url or '').rstrip('/') + '/'
        url = url_join(base, endpoint.lstrip('/'))
        headers = {
            'Authorization': f'Bearer {self.sudo().aeropay_api_key}',
            'Accept': 'application/json',
        }
        try:
            response = requests.request(
                method, url, json=payload, params=params, headers=headers,
                timeout=self.aeropay_timeout or 15,
            )
        except requests.exceptions.RequestException as e:
            _logger.exception("Aero.Pay: no se pudo contactar %s", url)
            raise ValidationError(_("Market Pay: no se pudo contactar el servidor de pagos.")) from e

        try:
            body = response.json()
        except ValueError:
            body = {}
        if not response.ok:
            _logger.warning("Aero.Pay %s %s -> %s %s", method, url, response.status_code, response.text[:500])
            message = body.get('message') or body.get('error') or f'HTTP {response.status_code}'
            raise ValidationError(_("Market Pay: %s", message))
        return body

    def action_aeropay_test_connection(self):
        """Botón «Probar conexión»: valida URL y key leyendo la lista de QR."""
        self.ensure_one()
        self._aeropay_request('GET', 'qr', params={'per_page': 1})
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _("Market Pay"),
                'message': _("Conexión correcta con Aero.Pay."),
                'type': 'success',
                'sticky': False,
            },
        }
