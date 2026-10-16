<form method="get" style="display:flex;gap:10px;align-items:end;margin-bottom:16px">
    <label>Desde<br><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></label>
    <label>Hasta<br><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></label>
    <button class="btn btn-primary" type="submit">Filtrar</button>
</form>
