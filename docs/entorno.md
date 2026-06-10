# Entorno de Producción

## Servidor

| Parámetro | Valor |
|-----------|-------|
| OS | Ubuntu 22.04 LTS (kernel 5.15.0-173) |
| Panel | AApanel |
| Dominio | micro.clouds.com.bo |
| Web root | `/www/wwwroot/micro.clouds.com.bo/` |
| CDN/Proxy | Cloudflare (activo, SSL full) |
| Disco | 97 GB total — 83 GB usados |
| RAM | 11 GB total |

## Servicios

| Servicio | Versión | Puerto / Socket |
|---------|---------|----------------|
| Nginx | AApanel default | 80 / 443 |
| PHP-FPM | 8.4.17 | `/tmp/php-cgi-84.sock` |
| MySQL | AApanel default | 127.0.0.1:3306 |
| Redis | 7.4.7 | 127.0.0.1:6379 |

## Rutas importantes

```
/www/server/php/84/bin/php          — PHP 8.4 CLI
/www/server/php/84/etc/php.ini      — PHP config (FPM)
/www/server/php/84/etc/php-cli.ini  — PHP config (CLI)
/www/server/nginx/conf/             — Nginx global config
/www/server/panel/vhost/nginx/      — Nginx vhosts por sitio
/www/wwwlogs/micro.clouds.com.bo.log        — Access log
/www/wwwlogs/micro.clouds.com.bo.error.log  — Error log
```

## Nginx — Vhost activo

Archivo: `/www/server/panel/vhost/nginx/micro.clouds.com.bo.conf`

Configuración relevante:
- SSL: certificado en `/www/server/panel/vhost/cert/micro.clouds.com.bo/`
- URL rewriting: `try_files $uri $uri/ /index.php?$args` activo
- PHP-FPM: via socket `/tmp/php-cgi-84.sock`
- HSTS: `Strict-Transport-Security max-age=31536000`

## Base de datos

| Parámetro | Valor |
|-----------|-------|
| Motor | MySQL (InnoDB) |
| Host | 127.0.0.1:3306 |
| Database | `sql_micro` |
| Usuario | `sql_micro` |
| Contraseña | ver `.env` |

## Redis

| Parámetro | Valor |
|-----------|-------|
| Host | 127.0.0.1:6379 |
| Contraseña | ver `.env` |
| Uso | Cache + Sessions |
| Driver cache | `redis` |
| Driver session | `redis` |

Contraseña real en `/www/server/redis/redis.conf` → `requirepass`.

## PHP — Extensiones instaladas relevantes

- mbstring, intl, pdo_mysql, mysqli
- gd, curl, openssl, sodium
- redis (compilado via PECL 6.3.0)
- opcache, zip, xml, json

## Comandos frecuentes del entorno

```bash
# Artisan (SIEMPRE usar PHP 8.4)
/www/server/php/84/bin/php artisan <comando>

# Recargar Nginx
nginx -s reload

# Reiniciar PHP-FPM 8.4
systemctl restart php-fpm-84

# Ver logs de error en tiempo real
tail -f /www/wwwlogs/micro.clouds.com.bo.error.log

# Ver logs de Laravel
tail -f /www/wwwroot/micro.clouds.com.bo/storage/logs/laravel.log

# Redis CLI
redis-cli -a $(grep requirepass /www/server/redis/redis.conf | awk '{print $2}')
```

## Variables de entorno (.env)

El archivo `.env` está en `/www/wwwroot/micro.clouds.com.bo/.env`.

> **Importante:** No commitear `.env` — ya está en `.gitignore`.

Variables clave a configurar en un nuevo deploy:
- `APP_KEY` — generar con `php artisan key:generate`
- `DB_*` — credenciales de MySQL
- `REDIS_PASSWORD` — contraseña de Redis
- `APP_URL` — URL pública del sitio
- `ACTIVE_THEME` — tema activo de CMS
