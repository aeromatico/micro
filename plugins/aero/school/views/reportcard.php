<?php
/** @var array $data */
/** @var callable $esc */
$s = $data['student']; $c = $data['course']; $y = $data['year']; $cfg = $data['settings'];
$P = $data['periods']; $q = $data['qualitative']; $pass = $data['passing'];
$short = [1 => '1er Trim.', 2 => '2do Trim.', 3 => '3er Trim.', 4 => '4to', 5 => '5to', 6 => '6to'];
$shifts = ['manana' => 'Mañana', 'tarde' => 'Tarde', 'noche' => 'Noche'];
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><title>Boletín — <?= $esc($s->full_name) ?></title>
<style>
  @page { margin: 28px 34px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1a1a1a; }
  h1 { font-size: 13px; margin: 0; text-align: center; letter-spacing: .5px; }
  h2 { font-size: 11px; margin: 2px 0 0; text-align: center; font-weight: normal; }
  .center { text-align: center; }
  .head { margin-bottom: 8px; border-bottom: 2px solid #1a1a1a; padding-bottom: 6px; }
  table { width: 100%; border-collapse: collapse; }
  .data td { padding: 2px 4px; }
  .data .l { color: #555; width: 18%; }
  .grid th, .grid td { border: 1px solid #444; padding: 4px 5px; }
  .grid th { background: #e9e9e9; font-size: 9px; }
  .grid td.n { text-align: center; }
  .low { color: #b00020; font-weight: bold; }
  .field td { background: #f3f3f3; font-weight: bold; }
  .box { border: 1px solid #444; padding: 6px 8px; margin-top: 10px; }
  .sign { margin-top: 56px; }
  .sign td { text-align: center; width: 33%; padding-top: 4px; border-top: 1px solid #444; font-size: 9px; }
  .small { font-size: 8px; color: #666; }
</style></head><body>
<div class="head">
  <h2>ESTADO PLURINACIONAL DE BOLIVIA</h2>
  <h2>MINISTERIO DE EDUCACIÓN</h2>
  <h1>BOLETÍN DE CALIFICACIONES — GESTIÓN <?= $esc($y?->name ?? date('Y')) ?></h1>
</div>

<table class="data">
  <tr><td class="l">Unidad educativa:</td><td><?= $esc($cfg->school_name ?: \Aero\School\Classes\Notifier::schoolName($s->tenant_id)) ?></td>
      <td class="l">Código SIE:</td><td><?= $esc($cfg->sie_code ?: '—') ?></td></tr>
  <tr><td class="l">Distrito / Ciudad:</td><td><?= $esc(trim(($cfg->district ?: '') . ' ' . ($cfg->city ?: '')) ?: '—') ?></td>
      <td class="l">Nivel:</td><td><?= $esc($c->level_label) ?></td></tr>
  <tr><td class="l">Estudiante:</td><td><strong><?= $esc($s->full_name) ?></strong></td>
      <td class="l">RUDE:</td><td><?= $esc($s->rude ?: '—') ?></td></tr>
  <tr><td class="l">Curso:</td><td><?= $esc($c->name) ?></td>
      <td class="l">Turno:</td><td><?= $esc($shifts[$c->shift] ?? $c->shift) ?></td></tr>
  <tr><td class="l">C.I.:</td><td><?= $esc($s->document ?: '—') ?></td>
      <td class="l">Fecha de nac.:</td><td><?= $s->birthdate ? $esc($s->birthdate->format('d/m/Y')) : '—' ?></td></tr>
</table>

<br>
<table class="grid">
  <thead><tr>
    <th style="text-align:left">Campo / Área de saberes y conocimientos</th>
    <?php for ($p = 1; $p <= $P; $p++): ?><th><?= $esc($short[$p] ?? ($p . '°')) ?></th><?php endfor; ?>
    <?php if (!$q): ?><th>Promedio anual</th><th>Resultado</th><?php endif; ?>
  </tr></thead>
  <tbody>
  <?php $lastField = null; foreach ($data['rows'] as $r): ?>
    <?php if ($r['field'] && $r['field'] !== $lastField): $lastField = $r['field']; ?>
      <tr class="field"><td colspan="<?= $q ? 1 + $P : 3 + $P ?>"><?= $esc(mb_strtoupper($r['field'])) ?></td></tr>
    <?php endif; ?>
    <tr>
      <td><?= $esc($r['subject']) ?></td>
      <?php for ($p = 1; $p <= $P; $p++): ?>
        <?php if ($q): ?><td><?= $esc($r['qualitative'][$p] ?? '') ?></td>
        <?php else: $n = $r['notes'][$p]; ?><td class="n <?= $n !== null && $n < $pass ? 'low' : '' ?>"><?= $n !== null ? $esc((int) $n) : '' ?></td><?php endif; ?>
      <?php endfor; ?>
      <?php if (!$q): ?>
        <td class="n <?= $r['annual'] !== null && !$r['approved'] ? 'low' : '' ?>"><strong><?= $r['annual'] !== null ? $esc($r['annual']) : '' ?></strong></td>
        <td class="n"><?= $r['approved'] === null ? '' : ($r['approved'] ? 'Aprobado' : 'Reprobado') ?></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (!$data['rows']): ?><tr><td colspan="<?= $q ? 1 + $P : 3 + $P ?>" class="center">Aún no hay áreas registradas para este curso.</td></tr><?php endif; ?>
  </tbody>
</table>

<?php if (!$q): ?>
<div class="box">
  <strong>Promedio general:</strong> <?= $data['average'] !== null ? $esc($data['average']) : '—' ?> &nbsp;·&nbsp;
  <strong>Resultado:</strong> <?= $esc($data['result']) ?> &nbsp;·&nbsp;
  <span class="small">Nota mínima de aprobación: <?= $esc($pass) ?>/100</span>
</div>
<?php endif; ?>

<?php $a = $data['attendance']; if ($a['total']): ?>
<div class="box">
  <strong>Asistencia:</strong> <?= $esc($a['pct']) ?>% · Presentes <?= $esc($a['present']) ?> · Faltas <?= $esc($a['absent']) ?> ·
  Atrasos <?= $esc($a['late']) ?> · Licencias <?= $esc($a['excused']) ?>
</div>
<?php endif; ?>

<table class="sign"><tr>
  <td>Maestro(a) / Tutor(a)<?= $c->tutor ? '<br>' . $esc($c->tutor) : '' ?></td>
  <td>Director(a)<?= $cfg->director ? '<br>' . $esc($cfg->director) : '' ?></td>
  <td>Padre / Madre / Tutor</td>
</tr></table>
<p class="small center">Emitido el <?= date('d/m/Y') ?> · Escala de valoración cuantitativa 0–100 (Ser 10, Saber 35, Hacer 35, Decidir 10, Autoevaluación 10).</p>
</body></html>
