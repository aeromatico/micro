# Gotchas de October/Aero (todos causaron fallos silenciosos reales)

| Trampa | Síntoma | Solución |
|---|---|---|
| Subcarpeta con mayúscula (`Models/`) | Clase no encontrada, sin error | Todas las subcarpetas en minúsculas |
| `version.yaml` con string plano | Migraciones no corren, sin error | Valor = lista: changelog + archivos `.php` existentes |
| Job sin `Dispatchable` | `::dispatch()` explota | `use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;` |
| `$casts => 'array'` + regla `array` | Validación falla con array real | Usar `$jsonable` (la regla `array` sí funciona) |
| Secretos en `SettingsModel` | Texto plano, sin mutators/Encryptable | Columna cifrada en un modelo real o Aero.Api key store; `RateLimiter` requiere import FQN |
| `config_filter.yaml` con `type: relation/group` | 500 | Solo `checkbox`, `switch`, `dropdown`, `widget` |
| Columna de lista `type: dropdown` | No existe | `selectable`; contar relaciones con `relationCount` |
| ReportWidget con `widget.php` | Widget en blanco | `{clase}/partials/_widget.php` |
| Controller sin `index/create/update.php` | Menú visible, página en blanco | Crear la vista de cada behavior |
| Plugin nuevo sin menú | Nada | `rm storage/cms/manifest.php` |
| Íconos inexistentes en menú/lista | Ítem sin ícono o error | Usa íconos que ya aparecen en otros `Plugin.php` (`grep -rhn "'icon'" plugins/aero/*/Plugin.php`) |
| `hasPermission()` | Superadmin bloqueado | `hasAccess()` |
| Pantalla de tenant sin grant | "Denegado" / menú oculto | Migración `grant_*_to_tenant_admin.php` en aero/sites + versión nueva |
| Renombrar plugin | 404 en backend (`$/aero/viejo/...` en config de controllers) | `grep -rn '\$/aero/viejo' plugins/` y reemplazar |
| Carbon 3 `diffInDays/Hours` | Valores con signo | `diffInDays($otra, true)` |
| artisan/cron como root | `storage/` root:root → 500 general | Ejecutar como `www`; `chown -R www:www storage` |
| `plugin:refresh`, `plugin:rollback`, `rollbackPlugin()` | **Borra datos de plugins no relacionados** | PROHIBIDO. Nueva versión + `october:migrate` |
| Asset de tema sin `?v=` | Cloudflare sirve 1 año la versión vieja | Subir `?v=` tras rebuild |
| Migración con `tenant_id` fijo en seeders | Datos en tenant equivocado | Resolver el tenant; nunca hardcodear ids |
| Cuenta WhatsApp/Zernio | `/v1/sms/messages` es solo SMS desde 2026-09 | No usarlo para WhatsApp fresco |
| `mb_split()` | Removida en PHP 8 | Polyfill en `bootstrap/app.php` |

## Patrón de integración blanda
```php
if (!class_exists(\Aero\Otro\Models\Cosa::class)) return;
Event::listen('aero.otro.algoOcurrio', fn ($x) => /* reaccionar */);
```
Nunca `use` de una clase de otro plugin fuera de ese guard. `$require` solo si el plugin no tiene sentido sin el otro.

## Migraciones
- Idempotentes (`Schema::hasTable/hasColumn`), `down()` solo borra lo propio.
- Seeds de datos (acciones de crédito, plantillas) con `firstOrCreate` y `class_exists` del plugin ajeno.
