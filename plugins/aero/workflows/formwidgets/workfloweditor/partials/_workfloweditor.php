<?php
/** @var \Aero\Workflows\FormWidgets\WorkflowEditor $this */
$safeJson = htmlspecialchars($graphJson, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php if ($this->previewMode): ?>
    <pre><?= $safeJson ?></pre>
<?php else: ?>
    <textarea id="<?= $textareaId ?>" name="<?= $fieldName ?>" style="display:none"><?= $safeJson ?></textarea>
    <div id="<?= $editorId ?>"></div>
    <div class="afe-mobile" style="display:none;padding:32px;text-align:center;border:1px dashed rgba(127,127,127,.35);border-radius:6px">
        El editor visual funciona mejor en computadora.
    </div>
    <script>
        (function () {
            var config = <?= $config ?>;
            function boot() {
                if (window.AeroFlowEditor) { window.AeroFlowEditor.mount(config); }
            }
            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
            // October recarga partes de la página por AJAX: reintentar al terminar.
            $(window).one('ajaxUpdateComplete', boot);
        })();
    </script>
<?php endif ?>
