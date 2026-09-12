<?php
/** @var Aero\Sites\Controllers\SiteSettings $this */
if (!empty($this->vars['noTenant'])): ?>
<div class="padded-container">
    <div class="alert alert-warning">
        <i class="icon-warning"></i>
        No hay ningún tenant asociado al sitio activo. Selecciona un sitio con tenant en el selector de sitios del backend.
    </div>
</div>
<?php return; endif;

$generalWidget       = $this->generalWidget;
$contactInfoWidget   = $this->contactInfoWidget;
$contactConfigWidget = $this->contactConfigWidget;
$seoWidget           = $this->seoWidget;
$channelFormWidget   = $this->channelFormWidget;
$channels            = $this->vars['channels'];
$submissions         = $this->vars['submissions'];

$statusLabels = [
    'pending' => ['label' => 'Pendiente', 'class' => 'warning'],
    'sent'    => ['label' => 'Enviado',   'class' => 'success'],
    'failed'  => ['label' => 'Fallido',   'class' => 'danger'],
    'partial' => ['label' => 'Parcial',   'class' => 'info'],
];
?>
<div class="layout-row">
    <div class="layout-cell">
        <div class="control-tabs master-tabs" data-control="tab">

            <!-- Tab nav -->
            <ul class="nav nav-tabs">
                <li class="active">
                    <a href="#tab-general" data-toggle="tab">
                        <i class="icon-cog"></i> General
                    </a>
                </li>
                <li>
                    <a href="#tab-contacto" data-toggle="tab">
                        <i class="icon-phone"></i> Contacto
                    </a>
                </li>
                <li>
                    <a href="#tab-formulario" data-toggle="tab">
                        <i class="icon-sliders"></i> Formulario
                    </a>
                </li>
                <li>
                    <a href="#tab-mensajes" data-toggle="tab">
                        <i class="icon-envelope"></i> Mensajes
                        <?php if ($submissions->isNotEmpty()): ?>
                        <span class="badge"><?= $submissions->count() ?></span>
                        <?php endif ?>
                    </a>
                </li>
                <li>
                    <a href="#tab-notificaciones" data-toggle="tab">
                        <i class="icon-bell"></i> Notificaciones
                    </a>
                </li>
                <li>
                    <a href="#tab-seo" data-toggle="tab">
                        <i class="icon-search"></i> SEO
                    </a>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ============================================================
                     TAB: GENERAL (rubro del negocio y otros ajustes generales
                     del sitio que no encajan en Branding/Contacto/SEO)
                     ============================================================ -->
                <div id="tab-general" class="tab-pane active">
                    <div class="layout padded-container">
                        <form data-request="onSaveGeneral" data-request-flash>
                            <?= $generalWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ============================================================
                     TAB: CONTACTO (datos de contacto mostrados en el sitio)
                     ============================================================ -->
                <div id="tab-contacto" class="tab-pane">
                    <div class="layout padded-container">
                        <form data-request="onSaveContactInfo" data-request-flash>
                            <?= $contactInfoWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar información de contacto
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ============================================================
                     TAB: FORMULARIO (comportamiento del form de contacto)
                     ============================================================ -->
                <div id="tab-formulario" class="tab-pane">
                    <div class="layout padded-container">
                        <form data-request="onSaveContactConfig" data-request-flash>
                            <?= $contactConfigWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar configuración
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ============================================================
                     TAB: MENSAJES (bandeja de envíos del formulario)
                     ============================================================ -->
                <div id="tab-mensajes" class="tab-pane">
                    <div class="layout padded-container">
                        <?php if ($submissions->isEmpty()): ?>
                        <p class="text-muted">No hay mensajes recibidos aún.</p>
                        <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Mensaje</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $s): ?>
                                <?php $st = $statusLabels[$s->status] ?? ['label' => $s->status, 'class' => 'default'] ?>
                                <tr>
                                    <td><?= e($s->name) ?></td>
                                    <td><?= e($s->email) ?></td>
                                    <td><?= e($s->phone) ?></td>
                                    <td style="max-width:300px">
                                        <span title="<?= e($s->message) ?>">
                                            <?= e(str($s->message)->limit(80)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="label label-<?= $st['class'] ?>">
                                            <?= $st['label'] ?>
                                        </span>
                                    </td>
                                    <td style="white-space:nowrap">
                                        <?= $s->created_at?->format('d/m/Y H:i') ?>
                                    </td>
                                </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                        <?php endif ?>
                    </div>
                </div>

                <!-- ============================================================
                     TAB: NOTIFICACIONES
                     ============================================================ -->
                <div id="tab-notificaciones" class="tab-pane">
                    <div class="layout padded-container">

                        <h4 style="margin-top:0">Canales de notificación</h4>
                        <p class="help-block" style="margin-bottom:16px">
                            Los mensajes del formulario de contacto se enviarán a través de los canales habilitados.
                        </p>

                        <!-- Lista de canales (se refresca por AJAX) -->
                        <div id="channel-list" style="margin-bottom:20px">
                            <?= $this->makePartial('channels_list', ['channels' => $channels]) ?>
                        </div>

                        <!-- Formulario agregar / editar canal -->
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h3 class="panel-title">
                                    <i class="icon-plus"></i> Agregar / editar canal
                                </h3>
                            </div>
                            <div class="panel-body">
                                <?php if ($channels->isEmpty()): ?>
                                <div class="alert alert-info" style="margin-bottom:16px">
                                    <strong>Tip Gmail:</strong> usa el host
                                    <code>smtp.gmail.com</code>, puerto <code>587</code>, cifrado <code>TLS</code>
                                    y genera una
                                    <a href="https://myaccount.google.com/apppasswords" target="_blank">
                                        contraseña de aplicación
                                    </a>
                                    (no uses tu contraseña de Google habitual).
                                </div>
                                <?php endif ?>

                                <form data-request="onSaveChannel" data-request-flash>
                                    <input type="hidden" name="channel_id" id="channel_id_field" value="">
                                    <div id="channel-form-inner">
                                        <?= $channelFormWidget->render() ?>
                                    </div>
                                    <div class="form-buttons">
                                        <button type="submit" class="btn btn-success" data-load-indicator="Guardando...">
                                            <i class="icon-check"></i> Guardar canal
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-default"
                                            onclick="
                                                document.querySelector('#channel_id_field').value = '';
                                                document.querySelector('#channel-form-inner').innerHTML = '';
                                            "
                                        >
                                            <i class="icon-times"></i> Cancelar edición
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ============================================================
                     TAB: SEO
                     ============================================================ -->
                <div id="tab-seo" class="tab-pane">
                    <div class="layout padded-container">
                        <form data-request="onSaveSeo" data-request-flash>
                            <?= $seoWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar configuración SEO
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div><!-- /.tab-content -->
        </div><!-- /.control-tabs -->
    </div>
</div>
