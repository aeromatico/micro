<?php /** @var int $log_id */ ?>
<div
    data-ai-pending
    data-log-id="<?= (int) $log_id ?>"
    data-started="<?= now()->timestamp ?>"
    style="display:flex; align-items:center; gap:14px; padding:16px 18px; border-radius:8px; border:1px solid rgba(99,102,241,.35); background:rgba(99,102,241,.08)"
>
    <i class="icon-spinner icon-spin" style="font-size:22px; color:#6366f1; flex-shrink:0"></i>
    <div>
        <strong>Generando tu página con IA<span data-ai-pending-dots>…</span></strong>
        <span data-ai-pending-elapsed style="opacity:.75"> (0s)</span>
        <p class="text-muted" style="margin:4px 0 0">
            Puede tardar hasta un minuto. No cierres esta pestaña — "Guardar" y "Rehacer con IA" quedan bloqueados mientras tanto para no perder el resultado, y la página se recarga sola apenas termine.
        </p>
    </div>
</div>
