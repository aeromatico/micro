<div data-control="toolbar">
    <button
        class="btn btn-default oc-icon-calculator"
        data-request="onRecalculateCosts"
        data-request-confirm="<?= e(trans('aero.hub::lang.endpoint.recalculate_confirm')) ?>"
        data-stripe-load-indicator>
        <?= e(trans('aero.hub::lang.endpoint.recalculate_button')) ?>
    </button>
</div>
