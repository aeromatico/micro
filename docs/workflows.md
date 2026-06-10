# Flujos de Trabajo

## Flujo 1 — Nuevo módulo de contenido (sin código PHP)

Usar **Tailor** cuando el contenido lo gestiona el cliente desde el admin:

```bash
# 1. Crear blueprint
/october-tailor entry Blog\Post title:string slug:string content:richtext published_at:date image:fileupload

# 2. Migrar para crear las tablas
/www/server/php/84/bin/php artisan october:migrate

# 3. Crear página CMS que muestra el contenido
/october-page "Blog" /blog default "Listado de posts con cards Pines"

# 4. Crear partials para los cards
/october-partial blog/post-card "Card de post con imagen, título, extracto y fecha"
```

## Flujo 2 — Nuevo plugin con CRUD completo

Cuando se necesita lógica de negocio propia:

```bash
# 1. Scaffold del plugin
/october-plugin Micro Ecommerce "Tienda online"

# 2. CRUD completo de un golpe (Model + Controller + YAMLs)
/october-crud Micro/Ecommerce Product \
  name:string slug:string price:decimal \
  stock:number description:richtext \
  image:fileupload is_active:checkbox

# 3. Agregar categorías y relacionarlas
/october-crud Micro/Ecommerce Category name:string slug:string
/october-relation Micro/Ecommerce Product belongsToMany Category

# 4. Añadir scopes de búsqueda y filtros
/october-scope Micro/Ecommerce Product filter status,price_range,category
/october-scope Micro/Ecommerce Product search name,description

# 5. Exponer API REST pública
/october-api Micro/Ecommerce Product index,show --public

# 6. Migrar
/www/server/php/84/bin/php artisan october:migrate
```

## Flujo 3 — Tarea asíncrona con notificación

```bash
# 1. Crear el Job
/october-job Micro/Ecommerce ProcessOrder --scheduled

# 2. Crear el evento que lo dispara
/october-event Micro/Ecommerce fire OrderPlaced

# 3. Crear el listener que hace el trabajo
/october-event Micro/Ecommerce listen OrderPlaced ProcessOrderListener

# 4. Email de confirmación
/october-mail Micro/Ecommerce OrderConfirmation --queue

# Worker en background (desarrollo)
/www/server/php/84/bin/php artisan queue:work redis --tries=3
```

## Flujo 4 — Nuevo componente frontend con Pines

```bash
# 1. Crear componente CMS (PHP) si necesita datos del servidor
/october-component Micro/Ecommerce FeaturedProducts "Productos destacados para homepage"

# 2. Crear partial Pines para visualización
/october-partial products/featured-grid "Grid de productos con hover effects y badge de descuento"

# 3. Crear página que los usa
/october-page "Tienda" /tienda default "Catálogo de productos con filtros laterales"

# 4. Agregar componentes Pines puntuales
/pines modal themes/demo/pages/tienda.htm "Modal de vista rápida del producto"

# 5. Compilar Tailwind
cd /www/wwwroot/micro.clouds.com.bo/themes/demo && npm run build
```

## Flujo 5 — Tarea programada

```bash
# 1. Crear el comando
/october-command Micro/Ecommerce SyncInventory shop:sync-inventory --scheduled "0 4 * * *"

# 2. Verificar que el cron del sistema está activo
crontab -l | grep artisan

# Si no está:
# Agregar: * * * * * /www/server/php/84/bin/php /www/wwwroot/micro.clouds.com.bo/artisan schedule:run >> /dev/null 2>&1

# 3. Ver tareas programadas
/www/server/php/84/bin/php artisan schedule:list
```

## Comandos del día a día

```bash
# Artisan — SIEMPRE usar PHP 8.4
/www/server/php/84/bin/php artisan <cmd>

# Alias útil para la sesión actual
alias pa="/www/server/php/84/bin/php /www/wwwroot/micro.clouds.com.bo/artisan"

# Migrar
pa october:migrate

# Limpiar cache
pa cache:clear && pa config:clear && pa route:clear

# Ver rutas
pa route:list --path=api/v1

# Ver logs en tiempo real
tail -f storage/logs/laravel.log

# Build Tailwind
cd themes/demo && npm run build
cd themes/demo && npm run dev  # watch mode

# Queue worker (desarrollo)
pa queue:work redis --sleep=3 --tries=3

# Jobs fallidos
pa queue:failed
pa queue:retry all
```

## Checklist para nuevo plugin

- [ ] Plugin.php con `pluginDetails()`, `registerComponents()`, `registerNavigation()`, `registerPermissions()`
- [ ] Al menos una migración en `updates/version.yaml`
- [ ] Modelos con `$fillable`, `$rules`, traits `Validation` + `SoftDelete`
- [ ] Controllers backend con `config_form.yaml` y `config_list.yaml`
- [ ] `fields.yaml` con todos los campos del formulario
- [ ] `columns.yaml` con columnas del listado
- [ ] `scopes.yaml` con filtros
- [ ] `_list_toolbar.htm` con botón "Nuevo"
- [ ] `lang/en/lang.php` con traducciones mínimas
- [ ] Ejecutar `october:migrate`
- [ ] Verificar en `/admin` que aparece el menú
