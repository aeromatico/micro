<?php $max = (int) ($formModel->style->max_seconds ?? ($formModel->style_id ? \Aero\Producer\Models\Style::find($formModel->style_id)?->max_seconds : 0) ?: 30) ?>
<p id="pr-counter" class="help-block" style="margin-top:-8px" data-max="<?= $max ?>" data-wps="<?= \Aero\Producer\Models\Order::WORDS_PER_SECOND ?>"></p>
<script>
    (function () {
        var box = document.getElementById('pr-counter');
        var area = document.querySelector('[name="Order[script]"]');
        if (!box || !area) return;
        function update() {
            var words = (area.value.trim().match(/\S+/g) || []).length;
            var secs = Math.ceil(words / parseFloat(box.dataset.wps));
            var max = parseInt(box.dataset.max, 10);
            box.textContent = words + ' palabras · unos ' + secs + ' s de ' + max + ' s permitidos';
            box.style.color = secs > max ? '#c0392b' : '';
        }
        area.addEventListener('input', update);
        update();
    })();
</script>
