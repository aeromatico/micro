import hashlib
import hmac
import logging

from werkzeug.exceptions import Forbidden

from odoo import http
from odoo.http import request
from odoo.addons.payment import utils as payment_utils

_logger = logging.getLogger(__name__)


class AeroPayController(http.Controller):
    _webhook_url = '/payment/aeropay/webhook'
    _pay_url = '/payment/aeropay/pay'
    _status_url = '/payment/aeropay/status'

    # Página con el QR y sondeo del estado ---------------------------------
    @http.route(_pay_url, type='http', auth='public', methods=['GET'], website=True, sitemap=False)
    def aeropay_pay_page(self, reference, access_token, **kwargs):
        tx = self._get_tx(reference, access_token)
        if tx.aeropay_redirect_url:  # proveedores de redirección (PayPal, cripto...)
            return request.redirect(tx.aeropay_redirect_url, local=False)
        return request.render('payment_aeropay.pay_page', {
            'tx': tx,
            'qr_image': tx.aeropay_qr_image,
            'access_token': access_token,
            'status_url': self._status_url,
            'done_url': '/payment/status',
        })

    # Sondeo desde el navegador: respaldo si el webhook se pierde ----------
    @http.route(_status_url, type='json', auth='public', methods=['POST'])
    def aeropay_status(self, reference, access_token):
        tx = self._get_tx(reference, access_token)
        if tx.state in ('draft', 'pending'):
            try:
                tx._aeropay_sync()
            except Exception:  # un fallo de red no debe romper el sondeo
                _logger.exception("Aero.Pay: fallo al sincronizar %s", reference)
        return {'state': tx.state}

    # Webhook firmado (Aero.Pay -> Odoo) ------------------------------------
    @http.route(_webhook_url, type='http', auth='public', methods=['POST'], csrf=False)
    def aeropay_webhook(self):
        raw = request.httprequest.get_data()
        signature = request.httprequest.headers.get('X-Pay-Signature', '')
        payload = request.get_json_data()
        qr = payload.get('data') or {}

        tx = request.env['payment.transaction'].sudo().search([
            ('provider_code', '=', 'aeropay'),
            ('provider_reference', '=', str(qr.get('id'))),
        ], limit=1)
        if not tx:
            return request.make_response('unknown', status=404)

        secret = (tx.provider_id.aeropay_webhook_secret or '').encode()
        expected = 'sha256=' + hmac.new(secret, raw, hashlib.sha256).hexdigest()
        if not hmac.compare_digest(expected, signature):
            _logger.warning("Aero.Pay: firma de webhook inválida para %s", tx.reference)
            raise Forbidden()

        # No se confía en el cuerpo: se vuelve a consultar el estado real.
        try:
            tx._aeropay_sync()
        except Exception:
            _logger.exception("Aero.Pay: fallo al procesar el webhook de %s", tx.reference)
            return request.make_response('error', status=500)  # Aero reintenta
        return request.make_response('ok', status=200)

    @staticmethod
    def _get_tx(reference, access_token):
        tx = request.env['payment.transaction'].sudo().search([
            ('reference', '=', reference), ('provider_code', '=', 'aeropay'),
        ], limit=1)
        if not tx or not payment_utils.check_access_token(access_token, reference, tx.amount):
            raise Forbidden()
        return tx
