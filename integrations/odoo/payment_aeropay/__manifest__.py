{
    'name': 'Pasarela de pago: Market Pay (QR Bolivia)',
    'version': '17.0.1.0.0',
    'category': 'Accounting/Payment Providers',
    'summary': 'Cobra en el e-commerce de Odoo con QR bancario a través de Aero.Pay (Market).',
    'description': """
Proveedor de pago para Odoo (website_sale / e-commerce) que emite el cobro en
Aero.Pay: muestra el QR bancario al cliente, confirma el pago por webhook firmado
(HMAC-SHA256) y por consulta directa a la API, y concilia la transacción de Odoo.
""",
    'author': 'Market',
    'website': 'https://market.com.bo',
    'depends': ['payment', 'website_sale'],
    'data': [
        'views/payment_provider_views.xml',
        'views/payment_aeropay_templates.xml',
        'data/payment_method_data.xml',
        'data/payment_provider_data.xml',
    ],
    'application': False,
    'license': 'LGPL-3',
    'post_init_hook': 'post_init_hook',
    'uninstall_hook': 'uninstall_hook',
}
