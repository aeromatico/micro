<?php
/** @var Aero\Sites\Controllers\ContentEditor $this */
$indexPageWidget     = $this->indexPageWidget;
$contactPageWidget   = $this->contactPageWidget;
$contactConfigWidget = $this->contactConfigWidget;
$indexPage           = $this->vars['indexPage'];
$contactPage         = $this->vars['contactPage'];
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
                    <a href="#tab-inicio" data-toggle="tab">
                        <i class="icon-home"></i> Inicio
                    </a>
                </li>
                <li>
                    <a href="#tab-contacto" data-toggle="tab">
                        <i class="icon-phone"></i> Contacto
                    </a>
                </li>
            </ul>

            <div class="tab-content">

                <!-- ============================================================
                     TAB: INICIO
                     ============================================================ -->
                <div id="tab-inicio" class="tab-pane active">
                    <div class="layout padded-container">
                        <?php if ($indexPage): ?>
                        <form data-request="index_onSaveIndex" data-request-flash>
                            <?= $indexPageWidget->render() ?>
                            <div class="form-buttons">
                                <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                    <i class="icon-check"></i> Guardar página de inicio
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            No se encontró la página de inicio para este sitio.
                        </div>
                        <?php endif ?>
                    </div>
                </div>

                <!-- ============================================================
                     TAB: CONTACTO  (sub-tabs: Página / Formulario / Mensajes)
                     ============================================================ -->
                <div id="tab-contacto" class="tab-pane">
                    <div class="control-tabs" data-control="tab">

                        <ul class="nav nav-tabs secondary-tabs">
                            <li class="active">
                                <a href="#subtab-pagina" data-toggle="tab">
                                    <i class="icon-file-text-o"></i> Página
                                </a>
                            </li>
                            <li>
                                <a href="#subtab-formulario" data-toggle="tab">
                                    <i class="icon-sliders"></i> Formulario
                                </a>
                            </li>
                            <li>
                                <a href="#subtab-mensajes" data-toggle="tab">
                                    <i class="icon-envelope"></i> Mensajes
                                    <?php if ($submissions->isNotEmpty()): ?>
                                    <span class="badge"><?= $submissions->count() ?></span>
                                    <?php endif ?>
                                </a>
                            </li>
                        </ul>

                        <div class="tab-content">

                            <!-- Sub-tab: Página de contacto -->
                            <div id="subtab-pagina" class="tab-pane active">
                                <div class="layout padded-container">
                                    <?php if ($contactPage): ?>
                                    <form data-request="index_onSaveContactPage" data-request-flash>
                                        <?= $contactPageWidget->render() ?>
                                        <div class="form-buttons">
                                            <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                                <i class="icon-check"></i> Guardar página de contacto
                                            </button>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                    <div class="alert alert-warning">
                                        No se encontró la página de contacto para este sitio.
                                    </div>
                                    <?php endif ?>
                                </div>
                            </div>

                            <!-- Sub-tab: Configuración del formulario -->
                            <div id="subtab-formulario" class="tab-pane">
                                <div class="layout padded-container">
                                    <form data-request="index_onSaveContactConfig" data-request-flash>
                                        <?= $contactConfigWidget->render() ?>
                                        <div class="form-buttons">
                                            <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
                                                <i class="icon-check"></i> Guardar configuración
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Sub-tab: Mensajes recibidos -->
                            <div id="subtab-mensajes" class="tab-pane">
                                <div class="layout padded-container">
                                    <?php if ($submissions->isEmpty()): ?>
                                    <div class="alert alert-info">
                                        <i class="icon-envelope-o"></i>
                                        No hay mensajes recibidos aún.
                                    </div>
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

                        </div><!-- /.tab-content (sub-tabs) -->
                    </div><!-- /.control-tabs (sub-tabs) -->
                </div><!-- /#tab-contacto -->

            </div><!-- /.tab-content (main) -->
        </div><!-- /.control-tabs (main) -->
    </div>
</div>
