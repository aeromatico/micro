<div id="bot-rules-wrapper">
    <?php if ($formModel->exists): ?>
        <?= $this->relationRender('rules') ?>
    <?php else: ?>
        <p class="text-muted">Las reglas de palabras clave aparecen después de crear el bot.</p>
    <?php endif ?>
</div>

<script>
    // El trigger nativo solo condiciona por UN campo; acá hacen falta dos:
    // visible en "Chatbot", y en los modos IA solo si "Incluir respuestas
    // automáticas" está activo.
    (function () {
        var wrapper = document.getElementById('bot-rules-wrapper');
        var container = $(wrapper).closest('.form-group')[0] || wrapper;

        function refresh() {
            // El balloon-selector guarda su valor en un input hidden (no radio).
            var mode = $('input[type="hidden"][name="Bot[reply_mode]"]').val();
            var include = $('input[type="checkbox"][name="Bot[ai_include_rules]"]').is(':checked');
            var isAi = mode === 'ai' || mode === 'super_ai';
            var show = mode === 'autoresponder' || (isAi && include);
            $(container).toggle(show);
        }

        // jQuery (no addEventListener): el balloon-selector dispara su change con $.trigger.
        $(document).on('change', '[name="Bot[reply_mode]"], [name="Bot[ai_include_rules]"]', refresh);
        refresh();
    })();
</script>
