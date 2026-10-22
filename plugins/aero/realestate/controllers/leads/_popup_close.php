<?= Form::open() ?>
    <input type="hidden" name="id" value="<?= (int) $m->id ?>">
    <div class="modal-header">
        <button type="button" class="btn-close" data-dismiss="popup"></button>
        <h4 class="modal-title">Cerrar operación</h4>
    </div>
    <div class="modal-body">
        <div class="form-group"><label>Oferta cerrada</label><select name="offer_id" class="form-control" required><?php foreach (\Aero\Realestate\Models\Offer::visible()->when($m->property_id, fn ($q) => $q->where('property_id', $m->property_id))->whereIn('status', ['available', 'reserved'])->with('property')->get() as $o): ?><option value="<?= $o->id ?>" <?= $o->id == $m->offer_id ? 'selected' : '' ?>><?= e(($o->property?->title ?? '') . ' · ' . $o->modality_name . ' ' . number_format((float) $o->amount, 0, ',', '.') . ' ' . $o->currency) ?></option><?php endforeach ?></select></div>
        <div class="form-group"><label>Monto final (opcional)</label><input type="number" step="any" name="amount" class="form-control"><p class="help-block">Si es distinto al de la oferta. Sobre este monto se calcula la comisión.</p></div>
    </div>
    <div class="modal-footer">
        <button type="submit" class="btn btn-primary" data-request="onClose" data-request-flash data-hotkey="ctrl+enter, cmd+enter">Cerrar operación</button>
        <button type="button" class="btn btn-default" data-dismiss="popup">Cerrar</button>
    </div>
<?= Form::close() ?>
