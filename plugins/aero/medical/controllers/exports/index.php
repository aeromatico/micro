<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/exports') ?>">Exportar datos</a></li></ul>
<?php Block::endPut() ?>
<div class="form-preview" style="max-width:760px">
    <p>Los datos de pacientes y del negocio son <strong>siempre del negocio</strong>. La exportación es un ZIP con todo en JSON (notas, signos vitales,
        diagnósticos, recetas, órdenes, cuestionarios, vacunas, laboratorio y documentos, ya descifrados) y los archivos originales.</p>
    <p>La exportación de <strong>un paciente</strong> está en su ficha.</p>
    <?php if ($canBusiness): ?>
        <p><a class="btn btn-primary" href="<?= Backend::url('aero/medical/exports/business') ?>">Descargar exportación completa del negocio</a></p>
        <p class="text-muted">Puede tardar si hay muchos pacientes y archivos. Queda registrada en la bitácora. Guárdela en un lugar seguro: contiene datos de salud sin cifrar.</p>
    <?php else: ?>
        <p class="text-muted">La exportación completa del negocio solo la hace el propietario.</p>
    <?php endif ?>
</div>
