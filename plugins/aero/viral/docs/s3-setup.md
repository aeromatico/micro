# Viral · configurar el almacenamiento (S3)

Viral guarda **muestras de video** y **documentos KYC** en un bucket **privado**. El navegador sube
directo al bucket con URLs firmadas (no pasa por PHP ni por Cloudflare), por partes, hasta 100 MB por archivo.
Todo en `viral/` dentro del bucket (`viral/samples/…`, `viral/kyc/…`).

Sirve AWS S3 o cualquier compatible (Cloudflare R2, MinIO). Los secretos van **solo en `.env`**, nunca en el panel ni en el repo.

## 1. Crear el bucket
- Nombre libre (ej. `market-viral`), región cercana (ej. `us-east-1`; en R2 es `auto`).
- **Block all public access: activado** (las 4 casillas). Nada de ACL públicas.
- Cifrado por defecto: SSE-S3 (AES-256) activado.

## 2. CORS (obligatorio para subir desde el navegador)
Pegar `docs/s3/cors.json` en *Permissions → CORS*. Debe exponer `ETag` (sin eso la subida por partes falla).

    aws s3api put-bucket-cors --bucket NOMBRE --cors-configuration file://plugins/aero/viral/docs/s3/cors.json

## 3. Ciclo de vida (recomendado)
`docs/s3/lifecycle.json`: cancela subidas incompletas a los 2 días, red de seguridad que borra `viral/kyc/` a los 60 días Además borra `viral/drafts/` (subidas sin enviar) a los 2 días. CORS permite POST, PUT, GET y HEAD (las subidas son POST prefirmado directo del navegador).
(la app ya borra a los 30 tras decidir) y limpia los objetos de prueba.

    aws s3api put-bucket-lifecycle-configuration --bucket NOMBRE --lifecycle-configuration file://plugins/aero/viral/docs/s3/lifecycle.json

## 4. Usuario de acceso (IAM)
Crear un usuario **solo para Viral** con la política `docs/s3/iam-policy.json` (reemplazar `NOMBRE_DEL_BUCKET`)
y generar su *access key*. No reutilizar claves de otros servicios.

## 5. Variables en `.env`
    VIRAL_S3_KEY=AKIA…
    VIRAL_S3_SECRET=…
    VIRAL_S3_REGION=us-east-1
    VIRAL_S3_BUCKET=market-viral
    VIRAL_S3_ENDPOINT=              # vacío en AWS; en R2: https://<account>.r2.cloudflarestorage.com
    VIRAL_S3_PATH_STYLE=false       # true en MinIO
    VIRAL_S3_PREFIX=viral

(Si `VIRAL_S3_*` está vacío se usan `AWS_*`.) Luego:

    sudo -u www /www/server/php/84/bin/php artisan config:clear

## 6. Comprobar
    sudo -u www /www/server/php/84/bin/php artisan viral:s3-check

Prueba credenciales, escritura/lectura, URL firmada de lectura y de subida, subida por partes, CORS y bloqueo público.
Debe terminar en `✓ Todo listo`. Es seguro repetirlo (limpia lo que crea).

## Cloudflare R2 (alternativa)
Bucket en R2 → *Settings → CORS policy* con el mismo JSON, token de API con permiso *Object Read & Write* limitado al bucket,
`VIRAL_S3_REGION=auto`, `VIRAL_S3_ENDPOINT=https://<account>.r2.cloudflarestorage.com`. R2 no expone el bloqueo público por API:
el comando lo marca como aviso, no como error.

## Producción
`vendor/` no se versiona: tras el `git pull` correr `composer install --no-scripts` y `php artisan package:discover` como `www`.
