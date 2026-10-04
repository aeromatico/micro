<?php /** @var \Illuminate\Support\Collection $bots */ ?>
<?php if ($bots->isEmpty()): ?>
    <div class="alert alert-info"><?= e(trans('aero.telegram::lang.bots.empty')) ?></div>
<?php else: ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <th><?= e(trans('aero.telegram::lang.bots.name')) ?></th>
                <th><?= e(trans('aero.telegram::lang.bots.username')) ?></th>
                <th><?= e(trans('aero.telegram::lang.bots.status')) ?></th>
                <th style="width:180px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bots as $bot): ?>
            <tr>
                <td><?= e($bot->account->label ?? '—') ?></td>
                <td>@<?= e($bot->username ?? '—') ?></td>
                <td><?= e($bot->account->status ?? '—') ?></td>
                <td>
                    <button type="button" class="btn btn-default btn-sm"
                        data-request="onReconfigure"
                        data-request-data="id: <?= (int) $bot->id ?>">
                        <?= e(trans('aero.telegram::lang.bots.reconfigure')) ?>
                    </button>
                </td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
