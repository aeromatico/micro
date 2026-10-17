<?php /** @var \Aero\Medical\Models\Patient $p */ ?>
<style>.mf-more summary{cursor:pointer;font-weight:600;margin:0 0 8px}.mf-more{margin-bottom:12px}.mf-badge{display:inline-block;padding:1px 7px;border-radius:9px;font-size:11px;background:rgba(128,128,128,.25)}.mf-badge.over{background:#e53935;color:#fff}</style>
<div class="mf-card mf-more"><details open><summary>Vacunación</summary><div id="mf-vaccines"><?= $this->makePartial('vaccines', ['p' => $p]) ?></div></details></div>
<div class="mf-card mf-more"><details><summary>Agendar un control</summary><div id="mf-book"><?= $this->makePartial('book', ['p' => $p]) ?></div></details></div>
<div class="mf-card mf-more"><details open><summary>Imágenes, laboratorio y archivos</summary><div id="mf-studies"><?= $this->makePartial('studies', ['p' => $p]) ?></div></details></div>
<div class="mf-card mf-more"><details open><summary>Documentos</summary><div id="mf-documents"><?= $this->makePartial('documents', ['p' => $p]) ?></div></details></div>
<p><a class="btn btn-default btn-sm" href="<?= Backend::url('aero/medical/exports/patient/' . $p->id) ?>">Exportar todos los datos de este paciente (ZIP)</a></p>
<script>
document.addEventListener('ajax:done', function (e) { var f = e.target; if (f && f.classList && f.classList.contains('mc-reset')) f.reset(); });
document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-fill-book]');
    if (!b) return;
    var d = document.getElementById('book-date'), r = document.getElementById('book-reason');
    if (d) d.value = b.dataset.date + 'T09:00';
    if (r) r.value = b.dataset.reason;
    var det = document.getElementById('mf-book').closest('details'); if (det) det.open = true;
    document.getElementById('mf-book').scrollIntoView({behavior: 'smooth'});
});
</script>
