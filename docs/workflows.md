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

## Flujo 6 — Agregar variantes de diseño a un bloque Puck (aero/sites)

Patrón usado para dar 5 variantes visuales a un bloque del editor Puck
(`plugins/aero/sites/assets/puck-editor/src/components.jsx`), aplicado ya en
Hero, FeatureGrid, CTASection, Pricing, FAQ y Tabs. Cada bloque tiene **dos
renderers que deben quedar espejados a mano** (no hay build step que los
sincronice):

1. **JSX** (`components.jsx`) — usado por el editor visual (Vite → bundle
   consumido por el FormWidget).
2. **PHP** (`plugins/aero/sites/classes/ai/PuckHtmlRenderer.php`, método
   `render<Type>()`) — usado por `HeadlessRenderer` para generar HTML sin
   Node (páginas creadas por IA). Debe producir el mismo HTML/clases que el
   JSX, rama por rama.

```bash
# 1. Editar components.jsx: agregar fields nuevos (opcionales, sin romper
#    los existentes), un <Type>_VARIANT_OPTIONS y las ramas del render()
#    para cada variante (if (variant === '...') return (...)). La rama
#    default/backward-compat DEBE verse igual que antes cuando los campos
#    nuevos vienen vacíos — no se puede renombrar ni quitar props existentes,
#    porque hay `puck_data` ya guardado en BD con la forma vieja.

# 2. Espejar cada rama en PuckHtmlRenderer.php (mismo orden, mismas clases
#    Tailwind literales). Si hace falta un helper (parsear un textarea tipo
#    "uno por línea", armar un botón con contraste, etc.) agregarlo como
#    protected method reusable, mirror del helper del JSX.
php -l plugins/aero/sites/classes/ai/PuckHtmlRenderer.php

# 3. Registrar el catálogo de variantes para la Galería de Componentes
#    (/admin/aero/sites/componentgallery, superadmin-only):
#    agregar <TYPE>_VARIANTS + <TYPE>_DEFAULT_PROPS y una entrada en
#    self::BLOCKS en plugins/aero/sites/controllers/ComponentGallery.php
php -l plugins/aero/sites/controllers/ComponentGallery.php

# 4. Safelist de Tailwind — CRÍTICO, ver "Gotchas" abajo.
#    Editar themes/microsites/tailwind.config.js (safelist) con toda clase
#    Tailwind nueva usada en el render (dinámica, no aparece en ningún .htm
#    escaneado por `content`).

# 5. Build (los 3, en este orden si tocaste JSX + CSS):
cd plugins/aero/sites/assets/puck-editor && npm run build   # bundle del editor + catalog.json
cd ../../../../../themes/microsites && npm run build         # Tailwind (safelist + hand-written CSS)

# 6. Verificar el render headless (sin abrir navegador):
pa tinker --execute="
\$r = new Aero\Sites\Classes\Ai\HeadlessRenderer();
\$props = Aero\Sites\Controllers\ComponentGallery::<TYPE>_DEFAULT_PROPS;
foreach (array_keys(Aero\Sites\Controllers\ComponentGallery::<TYPE>_VARIANTS) as \$v) {
  \$props['variant'] = \$v;
  echo \$v.': '.strlen(\$r->render(['content'=>[['type'=>'<Type>','props'=>\$props]],'root'=>['props'=>[]]])).' bytes'.PHP_EOL;
}
"

# 7. Verificación visual real (backend + editor), con usuario temporal:
pa tinker --execute="
\$u = new Backend\Models\User();
\$u->login='debug_check'; \$u->email='debug@example.com';
\$u->password='TempPass123!'; \$u->password_confirmation='TempPass123!';
\$u->first_name='Debug'; \$u->last_name='Check'; \$u->is_superuser=true; \$u->save();
"
# Playwright headless contra /admin/aero/sites/componentgallery/preview?block=<Type>&variant=<v>
# y contra una página real en /admin/aero/sites/pages/update/<id> (ver el bloque
# en el canvas y hacer click para confirmar que el panel de props de la derecha
# muestra los campos nuevos). Borrar el usuario apenas termine la verificación:
pa tinker --execute="Backend\Models\User::where('login','debug_check')->first()?->forceDelete();"
```

### Gotchas (todos mordieron ya al menos una vez)

- **Tailwind purga clases hand-written en `@layer base/components` también**,
  no solo utilidades. Si agregás CSS propio (no generado por Tailwind) para
  un mecanismo nuevo — como el truco `:has()` + radios ocultos de Tabs en
  `themes/microsites/assets/css/app.css` — esas clases (`puck-tabs-panel-0`,
  etc.) tienen que estar en `safelist` igual que cualquier clase dinámica,
  o Tailwind las elimina del build aunque estén escritas en el CSS fuente.
- **CDN (Cloudflare) cachea `app.min.css` por 12h.** Cualquier URL que sirva
  ese archivo sin query de cache-busting (`?v=<hash de filemtime>`) puede
  mostrar CSS viejo tras un rebuild. El layout real del theme ya lo hace
  (`sitesSeo.cssVersion`) y `ComponentGallery::buildStandaloneDocument()`
  también (agregado al arreglar el bug de padding de Pricing). El FormWidget
  del editor (`PuckEditor::loadAssets()`) también versiona su JS/CSS por
  `filemtime` — no tocar ese patrón.
- **CSS hand-written de mecanismos interactivos hay que duplicarlo en DOS
  builds, no uno.** `themes/microsites/assets/css/app.css` stylea el sitio
  publicado, pero el editor visual (`/admin/aero/sites/pages/update/N` o
  `/mi-sitio`) NO carga ese archivo — usa su propio bundle
  (`plugins/aero/sites/assets/puck-editor/src/editor.css`, compilado con
  `tailwind.editor.config.js`). Si agregás una clase hand-written nueva
  (`.puck-tabs-*`, `.puck-lightbox`, etc.) solo en `app.css`, el bloque se ve
  perfecto en el sitio real y en la Galería de componentes, pero se ve roto
  en el editor (ya pasó con Tabs: todos los paneles se veían apilados solo
  ahí). Copiar el bloque de CSS a los dos archivos — en `editor.css` sin
  `@layer` (ese archivo es solo `@tailwind utilities;`, y un `@layer` vacío
  ahí no aportaría nada).
- **Íconos Tabler en PHP.** `PuckHtmlRenderer::pickedIconHtml($icon,
  $className, $size)` detecta el prefijo `tabler:` y renderiza el SVG
  inline usando `TablerIconPaths.php` (paths extraídos de
  `@tabler/icons-react`); si no es Tabler, cae a un `<span>` con el
  emoji/texto. Usar SIEMPRE este helper para renderizar `icon` — nunca
  `$this->e($icon)` suelto en un `<span>`, porque un ícono Tabler
  guardado se mostraría como texto literal "tabler:nombre". Si se agrega un
  ícono a `icons.js` que no esté en `TablerIconPaths.php`, regenerar ese
  archivo (extraer `__iconNode` del `.mjs` correspondiente en
  `node_modules/@tabler/icons-react/dist/esm/icons/`).
- **Sin JavaScript propio.** Todo lo interactivo (acordeones, tabs) tiene
  que resolverse con HTML/CSS nativo (`<details>`, `:has()`, `:checked`)
  porque `PuckHtmlRenderer.php` solo puede emitir HTML estático — no hay
  Node disponible en el flujo de generación con IA.
- **El panel de campos de un `array` en Puck muestra los items colapsados**
  (solo el summary). Si un campo nuevo dentro de `arrayFields` "no aparece"
  al mirar el bloque seleccionado, hay que abrir/expandir el item primero —
  no es un bug.
- **La lista de bloques del sidebar (`Blocks`) es scrolleable** y las
  categorías nuevas (ej. "Interactivo") quedan al final; si un bloque nuevo
  "no aparece" en el editor, probar scrollear el panel izquierdo antes de
  asumir que el build no llegó.
- **El campo `variant` va PRIMERO en `fields: {...}`**, antes que `title` y
  todo lo demás. Puck renderiza el panel de props en el mismo orden en que
  se declaran las keys del objeto `fields` — si `variant` queda al final
  (después de título/subtítulo/botones/imágenes), el usuario tiene que
  scrollear mucho para encontrarlo y parece que "no existe". Ya pasó una vez
  (Hero tenía `variant` casi al final, escondido bajo ~8 campos).
- **Verificar SIEMPRE con Playwright + usuario temporal**, no alcanza con
  `php -l` + render headless: el bug de Pricing (padding en blanco) y el de
  Tabs (pestañas que no cambiaban) solo se detectaron mirando el DOM real
  con `getComputedStyle`/clicks — el HTML generado por PHP puede ser
  perfecto y aun así verse roto por un problema de CSS/caché.

### Checklist para agregar variantes a un bloque

- [ ] `components.jsx`: fields nuevos opcionales + `<Type>_VARIANT_OPTIONS` + 5 ramas en `render()`
- [ ] `variant` es la PRIMERA key en `fields: {...}` (no al final, no se pierde en el scroll del panel)
- [ ] Rama default idéntica visualmente al comportamiento previo (compat con `puck_data` guardado)
- [ ] `PuckHtmlRenderer.php`: `render<Type>()` espejado rama por rama + `php -l`
- [ ] `ComponentGallery.php`: `<TYPE>_VARIANTS`, `<TYPE>_DEFAULT_PROPS`, entrada en `BLOCKS` + `php -l`
- [ ] `tailwind.config.js`: toda clase Tailwind nueva (y toda clase hand-written de CSS propio) en `safelist`
- [ ] `npm run build` en `assets/puck-editor/` (bundle + catalog.json) y en `themes/microsites/` (CSS)
- [ ] Render headless por variante vía tinker (bytes > 0, sin excepción)
- [ ] Verificación visual con Playwright + usuario temporal (borrado al terminar) en la Galería de Componentes **y** en un editor de página real

## Flujo 7 — Importación versátil (CSV/Excel/Pegar) en un controller

Infraestructura reutilizable en `aero/sites` para dar a cualquier listado del
proyecto una pantalla de importación con **tres modos**: CSV con
delimitador/enclosure/encoding configurables (nativo de October), MS Excel
(.xlsx/.xls), y "Copiar y Pegar" (celdas pegadas desde Excel/Sheets o texto
separado por comas). Implementado por primera vez en
`/admin/aero/crm/contacts/import` (botón "Importar" en el toolbar).

Piezas reutilizables (no tocar salvo que cambie el mecanismo para todos):

- `Aero\Sites\Behaviors\VersatileImportExportController` — extiende
  `Backend\Behaviors\ImportExportController` del núcleo. Agrega xlsx/xls a
  los tipos de archivo aceptados, inyecta la sección "Copiar y Pegar" en el
  formulario, y expone el handler AJAX `onImportFromPaste`.
- `Aero\Sites\Traits\VersatileImportModel` — se mezcla en el modelo de
  import concreto; convierte el archivo subido a CSV de forma transparente
  si es Excel (via PhpSpreadsheet), cacheado por ruta.
- `Aero\Sites\Models\TenantImportModel` — clase base abstracta para el
  modelo de import concreto; resuelve el tenant actual, recorre las filas
  ya parseadas y loguea creado/actualizado/error por fila. Solo hay que
  implementar `importRowForTenant(array $row, int $tenantId): bool`.

Para agregar import a otro modelo (Companies, Leads, Deals, u otro plugin):

```bash
# 1. Modelo de import concreto — un archivo nuevo, la lógica de creación/
#    actualización/dedupe es específica del modelo (ver Contact\ImportModel
#    como referencia: dedupe por email, firstOrCreate de la empresa, etc.)
#    plugins/<plugin>/models/<model>/ImportModel.php
#    namespace <Plugin>\Models\<Model>;
#    class ImportModel extends \Aero\Sites\Models\TenantImportModel {
#        protected function importRowForTenant(array $row, int $tenantId): bool { ... }
#    }

# 2. Columnas destino que el usuario puede emparejar (mismo formato que un
#    columns.yaml de lista, solo "campo: Etiqueta")
#    plugins/<plugin>/models/<model>/columns-import.yaml
#    plugins/<plugin>/models/<model>/fields-import.yaml   # normalmente: fields: []

# 3. Config del behavior en el controller
#    plugins/<plugin>/controllers/<model>/config_import_export.yaml
#    import:
#        title: Importar <modelo>
#        list: $/<plugin>/models/<model>/columns-import.yaml
#        form: $/<plugin>/models/<model>/fields-import.yaml
#        modelClass: <Plugin>\Models\<Model>\ImportModel
#        redirect: <plugin>/<model>
#        permissions: [<permiso.existente>]

# 4. Vista import.htm — copiar tal cual desde
#    plugins/aero/crm/controllers/contacts/import.htm (solo cambiar el
#    breadcrumb/label), envuelve $this->importRender() en
#    <div id="versatileImportContainer"> — el paste handler depende de ese ID.

# 5. Controller: agregar el behavior + la propiedad de config
#    public $implement = [..., \Aero\Sites\Behaviors\VersatileImportExportController::class];
#    public $importExportConfig = 'config_import_export.yaml';

# 6. Botón "Importar" en _list_toolbar.htm
#    Ui::button("Importar", '<plugin>/<model>/import')->icon('icon-cloud-upload')->secondary()

php -l plugins/<plugin>/models/<model>/ImportModel.php
```

**Gotchas verificados en la implementación de Contacts:**

- `ImportExportController` (y nuestra subclase) resuelven sus partials
  relativos a la carpeta de la clase concreta (`guessViewPath`), por eso
  `VersatileImportExportController::__construct()` agrega la carpeta de
  partials del núcleo (`modules/backend/behaviors/importexportcontroller/partials`)
  como ruta de búsqueda adicional — sin eso, `importRender()` no encuentra
  `_container_import.php`.
- `$this->vars` (usado por `_container_import.php` para
  `$importUploadFormWidget`, `$importDbColumns`, etc.) solo se llena en el
  GET inicial de la página vía `prepareImportVars()`, dentro de la acción
  `import()`. El handler `onImportFromPaste` corre como AJAX (nunca pasa
  por `import()`), así que debe llamar `$this->prepareImportVars()` a mano
  antes de `importRender()`.
- El botón de "Copiar y Pegar" no necesita JS propio: `data-request` +
  `data-request-update="{ '#versatileImportContainer': true }"` alcanza,
  siempre que la vista envuelva `importRender()` en ese ID.
- Verificar el flujo completo (paste → emparejar columnas → import → fila
  creada) sin navegador es posible con `php artisan tinker`, simulando
  `request()->setMethod('POST')` + `request()->merge([...])` y llamando
  `$controller->run('import')` seguido de
  `$controller->asExtension(VersatileImportExportController::class)`.

## Flujo 8 — Exponer una API para un módulo nuevo (aero/api)

`aero/api` es el gateway central de keys: una sola key por dueño, con checkboxes de permisos por área (`*` = todo, `modulo.*` = área completa, `modulo.accion` = puntual). El middleware `aero.api` ya está registrado globalmente por ese plugin — un módulo nuevo NO necesita su propio modelo de key, su propio middleware ni su propia pantalla de tokens. Ver [[project_api_plugin]].

1. En el `Plugin.php` del módulo nuevo, dentro de `boot()`, publicar los scopes:

   ```php
   protected function registerApiScopes(): void
   {
       if (!class_exists(\Aero\Api\Classes\ScopeRegistry::class)) {
           return;
       }

       Event::listen('aero.api.registerScopes', function () {
           return ['chatbots' => [
               'label'  => trans('aero.chatbots::lang.plugin.name'),
               'scopes' => [
                   'chatbots.bots.manage' => 'Administrar bots',
                   'chatbots.bots.read'   => 'Leer bots',
               ],
           ]];
       });
   }
   ```

   Preferí una clase `Classes/Api/Scopes.php` con constantes (`Scopes::BOTS_MANAGE`) en vez de strings sueltos, para no tipear el scope a mano en las rutas — así lo hacen `aero/hello` y `aero/qrbo`.

2. Proteger las rutas directamente con el middleware del gateway:

   ```php
   Route::prefix('api/v1/chatbots')->group(function () use ($scopes) {
       Route::post('bots', [BotsController::class, 'store'])->middleware('aero.api:' . $scopes::BOTS_MANAGE);
   });
   ```

3. En el controller, leer la key y su dueño (si la key está atada a uno) desde `$request->attributes`:

   ```php
   $key = $request->attributes->get('api_key');
   $owner = $request->attributes->get('api_owner'); // ej. un Tenant de Aero.Sites
   ```

4. Migrar (`october:migrate`) — no hace falta migración propia del módulo para esto, `aero_api_keys` ya existe.
5. Probar con una key emitida desde el backend en **Configuración → API keys**, marcando el scope nuevo en el checkbox (aparece solo, `ScopeRegistry` lo recoge del evento).

**Menú "Configuración" (backend):** `aero/api` registra un menú principal por tabs (`sideMenu`) en `Backend::url('aero/api/apikeys')`, cuyo primer tab es la gestión unificada de keys. Es el lugar para juntar pantallas de ajustes transversales de distintos plugins bajo un único ítem de navegación en vez de que cada uno abra su propio menú de nivel superior. Para agregar un tab nuevo (típicamente una pantalla de settings de tu plugin) sin tocar `aero/api`:

```php
Event::listen('backend.menu.extendItems', function ($manager) {
    $manager->addSideMenuItem('Aero.Api', 'configuracion', 'minuevotab', [
        'label'       => 'aero.mimodulo::lang.menu.settings',
        'icon'        => 'icon-sliders',
        'url'         => Backend::url('aero/mimodulo/settings'),
        'permissions' => ['aero.mimodulo.manage_settings'],
    ]);
});
```

El primer argumento siempre es `'Aero.Api'` (el dueño del menú) y el segundo siempre `'configuracion'` (su código) — son fijos, no del plugin que agrega el tab. **Importante:** el ítem principal "Configuración" solo se muestra a quien tenga alguno de los permisos listados en su propio `permissions` (hoy `aero.api.manage_keys`, `aero.shop.manage_settings`, `aero.sites.manage_seo`); si tu tab usa un permiso distinto, agregalo también a ese array en `plugins/aero/api/Plugin.php` o los usuarios con solo tu permiso no verán el menú.

**Patrón híbrido (adoptado 2026-09-06):** cuando el plugin ya tiene su propio menú de nivel superior con pantallas de contenido real (no solo settings) — como "Tienda" (productos, pedidos, clientes...) o "Sitio Web" (páginas, SEO...) — NO lo desarmamos ni movemos su Configuración de ahí. En vez de eso, el plugin agrega un tab **espejo** en "Configuración" que apunta al mismo controlador de settings, sin quitarlo de su menú propio. Así cada plugin sigue siendo dueño de su navegación, pero todos los ajustes quedan también accesibles desde un único lugar. Ver `registerConfigMenuTab()` en `plugins/aero/shop/Plugin.php` y `plugins/aero/sites/Plugin.php` como referencia — es el mismo snippet de arriba, solo que apunta a un controlador de settings que ya existía.

**Cuándo SÍ conviene un modelo de key propio además del gateway:** solo si el módulo debe seguir funcionando sin `aero/api` instalado (dependencia blanda) — en ese caso replicá el patrón de `KeyResolver` de `aero/qrbo` o `aero/hello` (intenta el gateway primero, cae al modelo propio si no está instalado). Para un módulo interno de este proyecto (donde `aero/api` siempre está presente) no hace falta: andá directo al gateway.

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
