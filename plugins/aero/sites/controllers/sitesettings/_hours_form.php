<?php
use Aero\Sites\Classes\BusinessHours;
/** @var array $businessHours  día => [[open, close], ...] (vacío = cerrado) */
/** @var bool $shopLinked */
$configured = (bool) array_filter($businessHours);
?>
<form data-request="onSaveHours" data-request-flash id="hours-form">
    <h4 style="margin-top:0">Horario de atención</h4>
    <p class="help-block" style="margin-bottom:16px">
        Marca los días que abres y las horas de apertura y cierre. Un segundo turno sirve para quienes cierran al mediodía.
        Se muestra en el pie de página y en el bloque «Horario de atención» (Contenido dinámico) de tus páginas.
    </p>

    <table class="table" style="max-width:760px">
        <thead>
            <tr>
                <th style="width:130px">Día</th>
                <th style="width:90px">Cerrado</th>
                <th>Turno 1</th>
                <th>Turno 2 <span class="text-muted">(opcional)</span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (BusinessHours::DAYS as $key => $label):
            $shifts = $businessHours[$key] ?? [];
            // Sin nada guardado aún, lunes a viernes de 8:00 a 18:00 como punto de partida.
            if (!$configured) {
                $shifts = in_array($key, ['sat', 'sun'], true) ? [] : [['08:00', '18:00']];
            }
            $closed = !$shifts;
        ?>
            <tr data-day="<?= $key ?>">
                <td><strong><?= e($label) ?></strong></td>
                <td>
                    <label style="margin:0">
                        <input type="checkbox" name="days[<?= $key ?>][closed]" value="1" <?= $closed ? 'checked' : '' ?>
                               onchange="hoursToggle(this)">
                        Cerrado
                    </label>
                </td>
                <?php for ($i = 0; $i < BusinessHours::MAX_SHIFTS; $i++): $s = $shifts[$i] ?? ['', '']; ?>
                <td style="white-space:nowrap">
                    <input type="time" class="form-control" style="display:inline-block;width:110px"
                           name="days[<?= $key ?>][shifts][<?= $i ?>][open]" value="<?= e($s[0]) ?>" <?= $closed ? 'disabled' : '' ?>>
                    <span class="text-muted">a</span>
                    <input type="time" class="form-control" style="display:inline-block;width:110px"
                           name="days[<?= $key ?>][shifts][<?= $i ?>][close]" value="<?= e($s[1]) ?>" <?= $closed ? 'disabled' : '' ?>>
                </td>
                <?php endfor ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>

    <p style="margin-bottom:16px">
        <button type="button" class="btn btn-default btn-sm" onclick="hoursCopyMonday()">
            <i class="icon-copy"></i> Copiar el lunes a martes–viernes
        </button>
    </p>

    <?php if ($shopLinked): ?>
    <div class="form-group">
        <label style="font-weight:normal">
            <input type="checkbox" name="apply_to_shop" value="1" checked>
            Usar este horario también para los pedidos de la tienda
        </label>
        <p class="help-block" style="margin:4px 0 0 20px">
            Fuera de horario la tienda muestra «Cerrado» y no acepta pedidos.
        </p>
    </div>
    <?php endif ?>

    <div class="form-buttons">
        <button type="submit" class="btn btn-primary" data-load-indicator="Guardando...">
            <i class="icon-check"></i> Guardar horario
        </button>
    </div>
</form>

<script>
function hoursToggle(box) {
    var row = box.closest('tr');
    row.querySelectorAll('input[type=time]').forEach(function (i) { i.disabled = box.checked; });
}
function hoursCopyMonday() {
    var form = document.getElementById('hours-form');
    var mon = form.querySelector('tr[data-day=mon]');
    if (mon.querySelector('input[type=checkbox]').checked) { return; }
    var src = mon.querySelectorAll('input[type=time]');
    ['tue', 'wed', 'thu', 'fri'].forEach(function (d) {
        var row = form.querySelector('tr[data-day=' + d + ']');
        var box = row.querySelector('input[type=checkbox]');
        box.checked = false;
        row.querySelectorAll('input[type=time]').forEach(function (i, n) { i.disabled = false; i.value = src[n].value; });
    });
}
</script>
