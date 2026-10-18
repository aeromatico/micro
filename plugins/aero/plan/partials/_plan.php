<?php
use Aero\Plan\Classes\Stages;
use Aero\Plan\Models\Step;

/** @var \Aero\Plan\Models\Plan|null $plan */
$def = Stages::find($view);
?>
<?php if (!$plan): ?>
    <?php if ($isTeam): ?>
        <p class="aplan-note">Este plan no existe.</p>
    <?php else: ?>
        <div class="aplan-intro">
            <span class="aplan-eyebrow">Opcional</span>
            <h2>Tu sistema funcionando en 7 días</h2>
            <p>El Plan de 7 días es un camino de 5 pasos que recorremos juntos: tú nos cuentas cómo opera tu negocio, nosotros lo traducimos a Market, y en cada paso revisamos y aprobamos antes de avanzar. Puedes guardar tu avance y seguir otro día.</p>
            <p>No estás obligado a usarlo. Si prefieres hacerlo todo por tu cuenta, puedes dejarlo cuando quieras sin perder nada.</p>
            <button type="button" class="btn btn-primary" data-request="onStart" data-request-loading="#plan-root">Empezar el plan de 7 días</button>
        </div>
    <?php endif ?>
<?php return; endif ?>

<?php
$steps = $plan->steps->keyBy('number');
$step = $steps[$view];
$answers = $step->answers ?? [];
$editableClient = !$isTeam && $plan->status === 'active' && $step->isEditable();
$editableTeam = $isTeam && $plan->status === 'active' && in_array($step->status, ['open', 'review', 'changes'], true);
$missing = $step->status === 'locked' ? [] : Stages::missing($step->number, $answers);
$messages = $plan->messages->where('step_number', $view);
$approved = $plan->approved_count;
?>
<div class="aplan">
    <form id="plan-form" onsubmit="return false">
        <input type="hidden" name="view" value="<?= $view ?>">
        <input type="hidden" name="plan_id" value="<?= $plan->id ?>">

        <div class="aplan-day">
            <span><strong>Día <?= $plan->day ?> de 7</strong> · <?= $plan->status === 'paused' ? 'contador detenido mientras el plan está en pausa' : 'referencia, no una obligación' ?><?= $isTeam ? ' · ' . e($plan->tenant_name) : '' ?></span>
            <div class="aplan-meter"><i style="width: <?= $approved * 20 ?>%"></i></div>
            <span><?= $approved ?> de 5 pasos aprobados</span>
        </div>

        <?php if ($plan->status === 'paused'): ?>
            <div class="aplan-note warn">
                <b>Plan en pausa.</b>
                <?= $isTeam ? 'El dueño decidió seguir por su cuenta' : 'Elegiste seguir por tu cuenta' ?><?= $plan->paused_at ? ' el ' . $plan->paused_at->format('d/m H:i') : '' ?><?= $plan->leave_reason ? ' · ' . e($plan->leave_reason) : '' ?>.
                El avance está guardado y el contador de 7 días se detuvo.
                <?php if (!$isTeam): ?>
                    <div><button type="button" class="btn btn-primary" data-request="onResume">Retomar el plan</button></div>
                <?php endif ?>
            </div>
        <?php elseif ($plan->status === 'completed'): ?>
            <div class="aplan-note ok"><b>Plan completado en <?= $plan->day ?> <?= $plan->day === 1 ? 'día' : 'días' ?>.</b> El sistema está en marcha y seguimos a tu lado.</div>
        <?php endif ?>

        <div class="aplan-body">
            <nav class="aplan-rail" aria-label="Pasos del plan">
                <?php foreach (Stages::all() as $n => $s): $st = $steps[$n]; ?>
                    <button type="button" class="aplan-step s-<?= $st->status ?>" <?= $n === $view ? 'aria-current="step"' : '' ?>
                        data-request="onView" data-request-data="view: <?= $n ?>, plan_id: <?= $plan->id ?>">
                        <span class="aplan-num"><?= $st->status === 'approved' ? '✓' : $n ?></span>
                        <span><b><?= e($s['name']) ?></b><small><?= e($st->status_label) ?></small></span>
                    </button>
                <?php endforeach ?>
            </nav>

            <section class="aplan-panel">
                <div class="aplan-head">
                    <div>
                        <span class="aplan-eyebrow">Paso <?= $view ?> de 5</span>
                        <h2><?= e($def['name']) ?></h2>
                        <p class="aplan-goal"><?= e($def['goal']) ?></p>
                    </div>
                    <span class="aplan-badge b-<?= $step->status ?>"><?= e($step->status_label) ?></span>
                </div>

                <?php if ($plan->status === 'active'): ?>
                    <?php if ($step->status === 'review'): ?>
                        <div class="aplan-note info"><?= $isTeam ? '<b>Listo para tu revisión.</b> El dueño envió este paso. Revisa sus respuestas y aprueba o pide cambios.' : '<b>Enviado a revisión.</b> El equipo lo está revisando. Te avisaremos apenas haya respuesta.' ?></div>
                    <?php elseif ($step->status === 'changes'): $last = $messages->where('tag', 'changes')->last(); ?>
                        <div class="aplan-note warn"><b>El equipo pidió ajustes.</b> <?= $last ? e($last->body) : '' ?></div>
                    <?php elseif ($step->status === 'approved'): ?>
                        <div class="aplan-note ok"><b>Paso aprobado.</b> Queda como registro y ya no se edita.</div>
                    <?php elseif ($step->status === 'open' && $isTeam): ?>
                        <div class="aplan-note"><b>El dueño todavía está completando este paso.</b> Puedes escribirle y preparar tus secciones.</div>
                    <?php endif ?>
                <?php endif ?>

                <?php if ($step->status === 'locked'): ?>
                    <div class="aplan-note">Este paso se abre cuando se apruebe el paso <?= $view - 1 ?>. Esto es lo que se pedirá:</div>
                    <ul class="aplan-preview">
                        <?php foreach (Stages::fields($view, []) as $f): if (Stages::isTeamField($f)) continue; ?><li><?= e($f['l']) ?></li><?php endforeach ?>
                    </ul>
                <?php else: ?>
                    <div class="aplan-fields">
                        <?php foreach (Stages::fields($view, $answers) as $f): ?>
                            <?= $this->makePartial('$/aero/plan/partials/_field', [
                                'f' => $f, 'answers' => $answers, 'editableClient' => $editableClient,
                                'editableTeam' => $editableTeam, 'isTeam' => $isTeam,
                            ]) ?>
                        <?php endforeach ?>
                    </div>

                    <div class="aplan-bar">
                        <div id="plan-progress"><?= $this->makePartial('$/aero/plan/partials/_progress', ['step' => $step]) ?></div>
                        <div class="aplan-btns" id="plan-actions"><?= $this->makePartial('$/aero/plan/partials/_actions', [
                            'plan' => $plan, 'step' => $step, 'isTeam' => $isTeam, 'view' => $view,
                            'editableClient' => $editableClient, 'editableTeam' => $editableTeam,
                        ]) ?></div>
                    </div>

                    <section class="aplan-thread" aria-label="Conversación del paso">
                        <h3>Conversación</h3>
                        <div class="aplan-msgs">
                            <?php foreach ($messages as $m): ?>
                                <?php if ($m->author === 'system'): ?>
                                    <div class="aplan-msg sys"><?= e($m->body) ?> · <?= $m->created_at->format('d/m H:i') ?></div>
                                <?php else: ?>
                                    <div class="aplan-msg <?= $m->author ?>">
                                        <small><?= $m->author === 'team' ? 'Equipo Clouds' : ($isTeam ? 'Dueño' : 'Tú') ?> · <?= $m->created_at->format('d/m H:i') ?></small>
                                        <?= nl2br(e($m->body)) ?>
                                    </div>
                                <?php endif ?>
                            <?php endforeach ?>
                            <?php if ($messages->isEmpty()): ?><div class="aplan-hint">Aún no hay mensajes.</div><?php endif ?>
                        </div>
                        <?php if ($plan->status === 'active' && $step->status !== 'approved'): ?>
                            <div class="aplan-composer">
                                <label class="aplan-hint" for="plan-message"><?= $isTeam ? 'Escribes como equipo Clouds. Este texto también se usa al aprobar o pedir cambios.' : 'Escribe al equipo si tienes dudas.' ?></label>
                                <textarea id="plan-message" name="message" rows="2" maxlength="2000" placeholder="Escribe un mensaje"></textarea>
                                <button type="button" class="btn btn-default" data-request="onSend">Enviar</button>
                            </div>
                        <?php endif ?>
                    </section>

                    <?php if (!$isTeam && $plan->status === 'active' && $step->status !== 'approved'): ?>
                        <details class="aplan-leave">
                            <summary>Prefiero hacerlo por mi cuenta</summary>
                            <div class="aplan-leave-body">
                                <p>Está bien si prefieres seguir solo. No borramos nada: tu avance queda guardado, el contador se detiene y puedes retomarlo cuando quieras. Seguirás usando Market con normalidad.</p>
                                <label for="plan-reason">¿Quieres contarnos por qué? (opcional)</label>
                                <select id="plan-reason" name="reason">
                                    <option value="">Prefiero no decirlo</option>
                                    <option>Quiero avanzar a mi propio ritmo</option>
                                    <option>Ya tengo quien me ayude</option>
                                    <option>No es el momento</option>
                                    <option>Otro motivo</option>
                                </select>
                                <button type="button" class="btn btn-danger" data-request="onPause">Dejar el plan</button>
                            </div>
                        </details>
                    <?php endif ?>
                <?php endif ?>
            </section>
        </div>
    </form>
</div>
