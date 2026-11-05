<?php
use Aero\Tickets\Models\{Audit, Payment, Scan};
$u = $formModel;
$perms = array_keys(array_filter((array) ($u->role?->permissions ?? []))) ;
$tickPerms = array_values(array_filter($perms, fn ($p) => str_starts_with($p, 'aero.tickets.')));
$hist = Audit::where('actor_type', 'admin')->where('actor_id', $u->id)->orderByDesc('id')->limit(40)->get();
$verified = Payment::where('verified_by', $u->id)->count();
$scans = Scan::where('operator_type', 'admin')->where('operator_id', $u->id)->where('result', 'ok')->count();
$pays = \Aero\Tickets\Models\PaymentEvent::where('actor_type', 'admin')->where('actor_id', $u->id)->whereIn('action', ['approved', 'rejected', 'manual_registered', 'refunded'])->orderByDesc('id')->limit(20)->get();
?>
<div class="callout callout-<?= $u->isSuspendedNow() ? 'danger' : 'info' ?>"><div class="content">
    <p><strong><?= e($u->full_name_label) ?></strong> · <?= e($u->login) ?> · <?= e($u->email) ?> · <strong><?= e($u->is_suspended_label) ?></strong></p>
    <table class="table" style="max-width:640px">
        <tr><td>Rol</td><td><?= e($u->role_label) ?></td></tr>
        <tr><td>Organizador (espacios)</td><td><?= e($u->tenants_label) ?></td></tr>
        <tr><td>Permisos de Entradas</td><td><?= $u->is_superuser ? 'Todos' : (e(implode(', ', array_map(fn ($p) => str_replace('aero.tickets.', '', $p), $tickPerms))) ?: '—') ?></td></tr>
        <tr><td>Último acceso</td><td><?= e($u->last_login?->format('d/m/Y H:i') ?: 'nunca') ?></td></tr>
        <tr><td>Registrado</td><td><?= e($u->created_at?->format('d/m/Y H:i')) ?></td></tr>
        <tr><td>Pagos que verificó · ingresos que registró</td><td><?= $verified ?> · <?= $scans ?></td></tr>
    </table>
    <h5>Actividad crítica en la plataforma</h5>
    <table class="table"><thead><tr><th>Fecha</th><th>Acción</th><th>Detalle</th></tr></thead><tbody>
    <?php foreach ($hist as $h): ?><tr><td><?= e($h->created_at?->format('d/m/Y H:i')) ?></td><td><?= e($h->action) ?></td><td><?= e($h->note) ?><?php foreach ((array) $h->changes as $f => [$a, $b]): ?> <code><?= e($f) ?>: <?= e(mb_substr((string) $a, 0, 40)) ?> → <?= e(mb_substr((string) $b, 0, 40)) ?></code><?php endforeach ?></td></tr><?php endforeach ?>
    <?php foreach ($pays as $p): ?><tr><td><?= e($p->created_at?->format('d/m/Y H:i')) ?></td><td>pago: <?= e($p->action_label) ?></td><td><?= e($p->note) ?></td></tr><?php endforeach ?>
    <?php if ($hist->isEmpty() && $pays->isEmpty()): ?><tr><td colspan="3" class="text-muted">Sin actividad registrada.</td></tr><?php endif ?></tbody></table>
</div></div>
