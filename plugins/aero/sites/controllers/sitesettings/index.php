<?php
/** @var Aero\Sites\Controllers\SiteSettings $this */
$brandingWidget    = $this->brandingWidget;
$contactInfoWidget = $this->contactInfoWidget;
$seoWidget         = $this->seoWidget;
$channelFormWidget = $this->channelFormWidget;
$channels          = $this->vars['channels'];
?>
<div class="layout-row">
    <div class="layout-cell">
        <div class="control-tabs master-tabs" data-control="tab">

            <!-- Tab nav -->
            <ul class="nav nav-tabs">
                <li class="active">
                    <a href="#tab-branding" data-toggle="tab">
                        <i class="icon-image"></i> Branding
                    </a>
                </li>
                <li>
                    <a href="#tab-contacto" data-toggle="tab">
                        <i class="icon-phone"></i> Contacto
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
                     TAB: BRANDING
                     ============================================================ -->
                <div id="tab-branding" class="tab-pane active">
                    <div class="layout padded-container">
                        <form data-request="index_onSaveBranding" data-request-flash>
                            <?= $brandingWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar branding
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ============================================================
                     TAB: CONTACTO
                     ============================================================ -->
                <div id="tab-contacto" class="tab-pane">
                    <div class="layout padded-container">
                        <form data-request="index_onSaveContactInfo" data-request-flash>
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
                            <?= $this->makePartial('_channels_list', ['channels' => $channels]) ?>
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

                                <form data-request="index_onSaveChannel" data-request-flash>
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
                        <form data-request="index_onSaveSeo" data-request-flash>
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
