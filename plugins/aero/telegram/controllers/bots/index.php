<?php
/** @var \Aero\Telegram\Controllers\Bots $this */
$tenants = $this->vars['tenants'];
?>
<div class="layout-row">
    <div class="layout-cell padded-container">

        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="icon-paper-plane"></i> <?= e(trans('aero.telegram::lang.bots.connect_title')) ?></h3>
            </div>
            <div class="panel-body">
                <p class="text-muted"><?= e(trans('aero.telegram::lang.bots.connect_help')) ?></p>
                <form data-request="onConnect">
                    <div class="form-group">
                        <label for="telegram-token"><?= e(trans('aero.telegram::lang.bots.token')) ?></label>
                        <input type="password" id="telegram-token" name="token" class="form-control" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="telegram-tenant"><?= e(trans('aero.telegram::lang.bots.tenant')) ?></label>
                        <select id="telegram-tenant" name="tenant_id" class="form-control">
                            <?php foreach ($tenants as $id => $name): ?>
                                <option value="<?= e($id) ?>"><?= e($name) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= e(trans('aero.telegram::lang.bots.connect')) ?></button>
                </form>
            </div>
        </div>

        <h4><?= e(trans('aero.telegram::lang.bots.list_title')) ?></h4>
        <div id="telegram-bots">
            <?= $this->makePartial('list', ['bots' => $this->vars['bots']]) ?>
        </div>

    </div>
</div>
