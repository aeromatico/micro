# Rendimiento

Estado y configuración de rendimiento de la tienda. **Buena parte de esto vive
fuera del repositorio** (php.ini, php-fpm.conf, vhosts de nginx): si el servidor
se reconstruye, hay que volver a aplicarlo desde aquí.

Medido y aplicado el 2026-08-25. Perfilado de referencia: `/tienda` del tenant
`demo.market.com.bo`, 12 productos con imagen.

## Resultados

| Métrica | Antes | Después |
|---|---|---|
| p50 `/tienda` | 531 ms | 166 ms |
| p50 `/tienda/:coleccion` | 543 ms | 133 ms |
| p50 ficha de producto | 507 ms | 112 ms |
| Throughput (6 conexiones) | 3.8 req/s | ~16–19 req/s |
| Consultas por página | 25 | 19 |

El servidor aloja otros sitios, así que el throughput varía con el load general;
la latencia secuencial es la métrica estable para comparar.

## 1. OPcache — `/www/server/php/84/etc/php.ini`

Estaba **desactivado**: la extensión venía compilada (`--enable-opcache`) pero
`zend_extension=opcache` estaba comentado. PHP recompilaba ~950 archivos por
petición. Es, con diferencia, el cambio de mayor impacto.

```ini
zend_extension=opcache
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=30000
opcache.save_comments=1          ; OBLIGATORIO: October y Laravel leen docblocks
opcache.validate_timestamps=1
opcache.revalidate_freq=60       ; los deploys se reflejan solos en <=60s
opcache.fast_shutdown=1
```

`revalidate_freq=60` en lugar de `validate_timestamps=0` es una decisión
deliberada: evita tener que recargar FPM en cada deploy, a cambio de un ~5 % de
rendimiento. Si algún día el deploy recarga FPM de forma fiable, `=0` da ese
último tramo.

Verificar (a través de FPM, **no** por CLI — el CLI tiene OPcache desactivado a
propósito y siempre dirá que no está):

```bash
# desde un .php temporal en el docroot
php -r 'print_r(opcache_get_status(false)["opcache_statistics"]);'
```

Estado sano de referencia: ~3.900 scripts, hit rate >99 %, 139 MB de 256 MB,
`oom_restarts=0`. Si `oom_restarts` sube, agrandar `memory_consumption`.

## 2. Autoloader de Composer

```bash
composer dump-autoload --optimize
chown -R www:www vendor/composer
```

Pasó de 795 a 8.239 clases en el classmap. **Ejecutar en cada deploy.**

No se usó `--classmap-authoritative`: October registra clases en tiempo de
ejecución y el modo autoritativo puede romperlas.

## 3. Cachés de October / Laravel

```bash
php artisan config:cache      # queda en storage/framework/config.php
chown www:www storage/framework/config.php
```

Y en `.env` (que no está versionado):

```
CMS_ROUTE_CACHE=true
```

Con `config:cache` activo, **`.env` deja de leerse en runtime**: después de
cambiarlo hay que volver a ejecutar `php artisan config:cache`.

## 4. Pool de PHP-FPM — `/www/server/php/84/etc/php-fpm.conf`

```ini
pm.max_children = 60      ; era 200: a ~32 MB por worker eran 6.4 GB de 11.9 GB
pm.start_servers = 12
pm.min_spare_servers = 8
pm.max_spare_servers = 24
pm.max_requests = 500     ; no existía: un worker con fuga vivía para siempre
```

## 5. Cache de assets — nginx

Los tenants (`*.market.com.bo`) los sirve `market.com.bo.conf`, **no**
`micro.clouds.com.bo.conf`. Ambos tienen el mismo `root`, así que hay que aplicar
los cambios en los dos:

- `/www/server/panel/vhost/nginx/extension/market.com.bo/cache-assets.conf`
- `/www/server/panel/vhost/nginx/extension/micro.clouds.com.bo/cache-assets.conf`

El vhost de `market.com.bo` no incluía su carpeta de extensiones; se le añadió el
`include` **antes** de los `location` de assets del panel, porque nginx resuelve
los regex en orden de aparición.

Reglas:

| Ruta | Cache | Por qué |
|---|---|---|
| `/themes/**` | 1 año, `immutable` | Llevan huella `?v=<hash>` |
| `/storage/app/uploads/**` | 1 año, `immutable` | El nombre incluye id + dimensiones |
| `/modules/**`, `/plugins/**` | 7 días, `must-revalidate` | **No** llevan huella: inmutable dejaría JS viejo tras un update de October |

Brotli no está compilado en este nginx. Cloudflare lo aplica en el borde, así que
no hace falta recompilar.

## 6. Memoización del tenant

`Tenant::resolveFromDomain()` memoiza por host durante el request. Antes, cada
componente CMS que necesitaba el tenant (TenantSeo, PageList, PageDetail,
ContactSection, y los del shop vía `StorefrontContext`) disparaba su propio par
de consultas: se medían 4 `SELECT` a `aero_sites_domains` y 4 a
`aero_sites_tenants` por página. `afterSave`/`afterDelete` de `Tenant` y `Domain`
limpian la memoización.

## 7. Alpine.js local

Venía de `cdn.jsdelivr.net` con el rango flotante `@3.x.x`. Ahora se sirve desde
`themes/microsites/assets/js/alpine.min.js` con versión fija.

```bash
bash bin/alpine-update.sh   # actualiza y sincroniza la versión en base.htm
```

**El orden de carga importa**: `app.js` debe ir antes que `alpine.min.js` (ambos
`defer`). Alpine dispara `alpine:init` de forma síncrona apenas corre su script,
así que si va primero, `$store.nav` y `$store.theme` quedan sin registrar. Está
comentado en `base.htm`.

## Pendiente

- **HTML público en Cloudflare.** Todo sale con `cache-control: no-cache, private`
  y CF reporta `cf-cache-status: DYNAMIC`. Precondición: el badge del carrito se
  pinta en el servidor dentro del header, así que el HTML **no** es indiferente al
  usuario. Hay que sacarlo a una petición AJAX posterior a la carga —
  `shopCart::onAdd` ya devuelve ese fragmento.
- **Pregenerar miniaturas en cola.** Hoy `getThumbUrl()` genera cada miniatura
  bajo demanda: ~70 ms por imagen dentro del ciclo de petición. Con 12 productos
  la primera carga tras subir fotos tardó 1.3 s.
- **Autoalojar tipografías.** No se hizo: las fuentes son dinámicas por tenant
  (`Tenant::getGoogleFontsUrl()`), así que requiere un cache de fuentes por
  tenant, no una descarga estática.

## Trampa conocida: permisos de uploads

Si se crean archivos adjuntos desde CLI como `root`, FPM (que corre como `www`)
no puede escribir las miniaturas y `getThumbUrl()` devuelve **cadena vacía sin
error** — las imágenes salen con `src=""`. Después de cualquier script que
adjunte archivos:

```bash
chown -R www:www storage/app/uploads/public
```
