<?php

use Aero\Help\Models\Flow;
use Aero\Help\Models\Topic;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Primer flujo guiado: conectar una cuenta bancaria en Bolivia Pay. El
 * contenido sale de la documentación publicada (pay-cuentas-bancarias): si
 * algo cambia allí, se actualiza aquí desde Ayuda guiada → Flujos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_help_flows') || !Schema::hasTable('aero_help_topics')) {
            return;
        }

        $flow = Flow::where('slug', 'pay-conectar-cuenta')->first() ?: Flow::create([
            'slug'         => 'pay-conectar-cuenta',
            'title'        => 'Conectar una cuenta para cobrar',
            'plugin'       => 'pay',
            'intro'        => 'Te acompaño paso a paso para conectar una cuenta en **Bolivia Pay**. Lo que necesitas depende de tu banco o proveedor.',
            'is_published' => true,
            'steps'        => $this->steps(),
        ]);

        if (Topic::where('slug', 'pay-conectar-cuenta')->exists()) {
            return;
        }

        $docs = \Aero\Help\Classes\Integrations::docs();

        Topic::create([
            'slug'         => 'pay-conectar-cuenta',
            'title'        => 'Conectar una cuenta bancaria',
            'summary'      => 'Conecta la cuenta con la que vas a cobrar con QR: BNB, Banco Económico, correo, PayPal o criptomonedas.',
            'keywords'     => 'cuenta bancaria banco bnb economico paypal nowpayments correo gmail qr cobrar conectar credenciales sandbox produccion bolivia pay',
            'plugin'       => 'pay',
            'screens'      => [['pattern' => 'pay/*']],
            'flow_id'      => $flow->id,
            'article_id'   => $docs ? \Aero\Docs\Models\Article::platform()->where('slug', 'pay-cuentas-bancarias')->value('id') : null,
            'guide_id'     => $docs ? \Aero\Docs\Models\Guide::platform()->where('slug', 'guia-pay-bankaccounts')->value('id') : null,
            'sort_order'   => 10,
            'is_published' => true,
        ]);
    }

    public function down(): void
    {
        Topic::where('slug', 'pay-conectar-cuenta')->delete();
        Flow::where('slug', 'pay-conectar-cuenta')->delete();
    }

    protected function steps(): array
    {
        return [
            ['key' => 'banco', 'type' => 'choice', 'save_as' => 'banco',
                'body' => '**¿Con qué proveedor vas a cobrar?**',
                'options' => [
                    ['label' => 'BNB', 'value' => 'bnb', 'next' => 'datos_bnb'],
                    ['label' => 'Banco Económico', 'value' => 'economico', 'next' => 'datos_economico'],
                    ['label' => 'Otro banco o billetera (QR fijo)', 'value' => 'correo', 'next' => 'datos_correo'],
                    ['label' => 'PayPal', 'value' => 'paypal', 'next' => 'datos_paypal'],
                    ['label' => 'Criptomonedas (NOWPayments)', 'value' => 'nowpayments', 'next' => 'datos_now'],
                ]],

            ['key' => 'datos_bnb', 'type' => 'message', 'next' => 'abrir',
                'body' => "Para **BNB** necesitas estas llaves de tu integración: `accountId`, `authorizationId` y `destinationAccountId`. Opcionalmente `auth_base_url` y `qr_base_url`.\nTenlas a mano antes de seguir."],
            ['key' => 'datos_economico', 'type' => 'message', 'next' => 'abrir',
                'body' => "Para **Banco Económico** necesitas: `username`, `password`, `aes_key` y `account_credit` (y opcionalmente `base_url`).\nEl `branchCode` no va aquí: se indica en cada transacción (mira Sucursales en la documentación)."],
            ['key' => 'datos_correo', 'type' => 'message', 'next' => 'abrir',
                'body' => "Con esta opción cobras con tu **QR fijo** y el sistema detecta los pagos leyendo el correo del banco. Necesitas:\n- El banco o billetera (si no está en el catálogo, existe «Patrón manual»).\n- Un **correo Gmail** que reciba los avisos del banco.\n- Una **contraseña de aplicación de Gmail** de 16 caracteres (requiere tener la verificación en 2 pasos activada).\n- La **imagen del QR** y, si trae fecha impresa, su vencimiento.\n- Qué hacer si el pago no se detecta (por ejemplo, registrarlo a mano)."],
            ['key' => 'datos_paypal', 'type' => 'message', 'next' => 'abrir',
                'body' => 'Para **PayPal** necesitas: `client_id`, `client_secret`, `mode` (`sandbox` o `live`) y `webhook_id`.'],
            ['key' => 'datos_now', 'type' => 'message', 'next' => 'abrir',
                'body' => 'Para **NOWPayments** necesitas: `api_key` e `ipn_secret`.'],

            ['key' => 'abrir', 'type' => 'link', 'numbered' => true, 'title' => 'Abre el formulario', 'next' => 'campos',
                'body' => 'Cuando tengas esos datos, abre el formulario de nueva cuenta.',
                'label' => 'Abrir «Nueva cuenta»', 'ref' => 'backend:aero/pay/bankaccounts/create'],
            ['key' => 'campos', 'type' => 'message', 'numbered' => true, 'title' => 'Completa la configuración', 'next' => 'guia',
                'body' => "En la pestaña **Configuración**:\n- **Etiqueta**: un nombre para reconocerla, por ejemplo «Cuenta principal».\n- **Banco**: el proveedor que elegiste («{{banco}}»); si cobras con QR fijo, elige *Correo (sin API de banco)*.\n- **Entorno**: Sandbox / Certificación para probar, o Producción para cobrar de verdad. Una cuenta en sandbox nunca se elige sola como cuenta por defecto.\n- **Estado**: déjala **Activa**; solo las activas sirven para generar QR."],
            ['key' => 'guia', 'type' => 'guide', 'ref' => 'guia-pay-bankaccounts', 'next' => 'probar',
                'body' => '¿Prefieres practicar primero? Esta guía replica el formulario y te explica cada campo.'],
            ['key' => 'probar', 'type' => 'message', 'numbered' => true, 'title' => 'Guarda y prueba la conexión', 'next' => 'webhook',
                'body' => "Guarda la cuenta y usa la acción **Probar conexión**: consulta al proveedor y confirma que las credenciales funcionan.\nLas credenciales se guardan **cifradas**. Si luego editas el JSON, no borres claves existentes: el formulario reemplaza el contenido completo."],
            ['key' => 'webhook', 'type' => 'message', 'numbered' => true, 'title' => 'Webhooks (opcional)', 'next' => 'detalle',
                'body' => "En la pestaña **Webhooks** hay dos distintos:\n- **Entrante** (banco → tú): confirma el pago al instante. Se genera un secreto y se le entrega al banco junto con la URL.\n- **Saliente** (tú → tu sistema): envía un aviso a tu URL cuando un QR se paga, se anula o vence.\nSin el secreto entrante, la confirmación depende de la conciliación periódica y puede tardar un poco."],

            ['key' => 'detalle', 'type' => 'doc', 'ref' => 'pay-cuentas-bancarias', 'next' => 'video',
                'body' => 'Aquí tienes la documentación completa de este formulario.'],
            ['key' => 'video', 'type' => 'video', 'ref' => 'pay', 'next' => 'fin',
                'body' => 'Y un video corto de Bolivia Pay:'],
            ['key' => 'fin', 'type' => 'done',
                'body' => '**¿Pudiste conectar tu cuenta?**'],
        ];
    }
};
