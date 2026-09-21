Agrega "avisame por WhatsApp/correo" a un modelo — destinatario suelto (no un usuario del sistema), reusando el trait/partial/pipeline de Aero.Notify creados para Aero.Qrbo (`qrbo.qr.generated`, ver `plugins/aero/qrbo/controllers/QrCodes.php::sendQrAlerts()` como referencia end-to-end ya probada en producción).

## Usage
`/october-adhoc-alert <Vendor>/<Plugin> <Model> <event.code> [--attachment]`

**Ejemplo:**
`/october-adhoc-alert Aero/Crm CollectionItem crm.collection.reminder --attachment`

`--attachment` si el evento también manda un archivo (imagen/PDF) además de texto; omitilo para solo texto.

---

## Qué requiere de antemano

El plugin consumidor debe `$require` incluir `'Aero.Notify'` en su `Plugin.php` (si no lo tiene, agregalo).

## Piezas ya construidas — NO las reinventes

- `Aero\Notify\Traits\HasAdhocAlert` — da `getAlertPrefixOptions()`, `alert_whatsapp_number`, `hasAlertRecipient()`, `fireAlert(string $eventCode, array $context)`.
- `$/aero/notify/partials/_alert_recipient_fields.htm` — campo `type: partial` reusable, arma prefijo+WhatsApp+correo con el nombre del modelo resuelto en runtime (no hay que tocarlo por plugin).
- `WhatsAppDriver`/`EmailDriver` de Notify ya soportan adjuntos vía `$context`: `media_url`/`media_type` (whatsapp), `attachment_binary`/`attachment_filename`/`attachment_mime` o `attachment_url` (email).

## Qué SÍ hay que crear por plugin (no se puede compartir entre tablas)

### Paso 1 — Migración: 3 columnas en la tabla del modelo

`plugins/{vendor_lower}/{plugin_lower}/updates/add_alert_fields_to_{table}.php`:
```php
<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{table}', function ($table) {
            $table->string('alert_prefix', 8)->nullable()->default('+591');
            $table->string('alert_phone', 30)->nullable();
            $table->string('alert_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('{table}', function ($table) {
            $table->dropColumn(['alert_prefix', 'alert_phone', 'alert_email']);
        });
    }
};
```
Agregala a `updates/version.yaml` con un bump de versión.

### Paso 2 — Modelo: usar el trait

En `models/{Model}.php`:
```php
use \Aero\Notify\Traits\HasAdhocAlert;
```
Agregá `alert_prefix`, `alert_phone`, `alert_email` a `$fillable`.

### Paso 3 — Form: el campo compartido

En el `fields.yaml` (o `create_fields.yaml`) del modelo, dentro de una sección/tab dedicado:
```yaml
_section_alerts:
    label: Alerta (opcional)
    type: section
    comment: "Si completás alguno de estos, te avisamos apenas [describir el evento]."

_alert_recipient:
    type: partial
    path: $/aero/notify/partials/_alert_recipient_fields.htm
    span: full
```
Si el usuario pidió un tab separado (no una sección dentro del mismo tab), envolver `formRenderDesign()` + este bloque en `nav-tabs` manuales — ver `plugins/aero/chatbots/controllers/bots/update.htm` como referencia de ese patrón.

### Paso 4 — Evento nuevo en el catálogo de Notify

En `plugins/aero/notify/classes/EventCatalog.php`, dentro del grupo del plugin correspondiente (o uno nuevo), agregar:
```php
[
    'code' => '{event.code}',
    'source_plugin' => '{Vendor}.{Plugin}',
    'category' => '{categoria}',
    'name' => '{Nombre legible}',
    'description' => '{Qué dispara esto y por qué el destinatario es adhoc}',
    'priority' => 5,
    'default_audiences' => ['adhoc'],
    'default_channels' => ['whatsapp', 'email'],
    'variables_schema' => [
        // una entrada por variable que uses en la plantilla, {'type','required','label'}
    ],
    'sample_context' => [
        // valores de ejemplo para probar la plantilla desde el backend
    ],
],
```

### Paso 5 — Migración que siembra evento + reglas + plantillas

Copiar `plugins/aero/notify/updates/seed_qr_generated_templates.php` como molde exacto — cambiar el `code` del evento y el texto de las plantillas (twig, variables entre `{{ }}`). Agregarla a `plugins/aero/notify/updates/version.yaml` con bump de versión (esto es una migración de **Aero.Notify**, no del plugin consumidor, porque el catálogo/templates viven ahí).

### Paso 6 — Disparar el aviso tras persistir el registro

En el controller, después de guardar:
```php
if ($model->hasAlertRecipient()) {
    $context = [
        'variable_uno' => $model->campo,
        // ... el resto de variables_schema del evento
    ];

    if ($conAdjunto) { // solo si --attachment
        $context += [
            'media_url'           => url('/api/v1/{plugin}/public/.../image'), // endpoint público sin auth, ver QrCodesController::image
            'media_type'          => 'image',
            'attachment_binary'   => $model->getAdjuntoBinary(),
            'attachment_filename' => 'archivo.png',
            'attachment_mime'     => 'image/png',
        ];
    }

    $deliveries = $model->fireAlert('{event.code}', $context);

    foreach ($deliveries as $delivery) {
        if (in_array($delivery->status, ['failed', 'skipped'], true)) {
            Flash::warning("No se pudo mandar la alerta por {$delivery->channel}: {$delivery->error}");
        }
    }
}
```

Si `--attachment`, y el adjunto necesita URL pública para WhatsApp (Zernio no acepta data URIs ni headers de auth), agregar un endpoint público análogo a `QrCodesController::image()` — **ojo**: nunca terminar esa ruta en `.png`/`.jpg`/etc., el nginx de este VPS tiene un `location` sin `fastcgi_pass` para esas extensiones que rompe el status code (ver comentario en `plugins/aero/qrbo/Plugin.php`, ruta `public/qr/{reference}/image`).

### Paso 7 — Migrar y probar

```
sudo -u www /www/server/php/84/bin/php artisan october:migrate
```

Probar `hasAlertRecipient()`, render del form, y un envío real (crear un registro con `alert_phone`/`alert_email` cargado, revisar `Aero\Notify\Models\Delivery::orderByDesc('id')->first()` para confirmar `status=sent` y no `skipped`/`failed`).

Reportar: columnas agregadas, evento/plantillas sembrados, y si la prueba real de envío confirmó entrega.
