<?php /** @var Aero\Shop\Controllers\Kitchen $this */ ?>
<div class="padded-container">
    <?php if (!$isRestaurant): ?>
        <div class="alert alert-warning"><i class="icon-warning"></i>
            La pantalla de cocina está disponible cuando el tipo de tienda es «Restaurante» (Tienda → Configuración).
        </div>
    <?php else: ?>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h3 style="margin:0">Cocina</h3>
            <label class="checkbox custom-checkbox" style="margin:0">
                <input type="checkbox" id="kitchen-sound" checked> <label for="kitchen-sound">Sonido de pedido nuevo</label>
            </label>
        </div>
        <div id="kitchen-board"><?= $board ?></div>

        <script>
        (function () {
            var known = null;
            function beep() {
                try {
                    var c = new (window.AudioContext || window.webkitAudioContext)(), o = c.createOscillator(), g = c.createGain();
                    o.connect(g); g.connect(c.destination); o.frequency.value = 880; g.gain.value = 0.15;
                    o.start(); setTimeout(function () { o.stop(); c.close(); }, 450);
                } catch (e) {}
            }
            function check() {
                var el = document.getElementById('kitchen-board');
                var ids = JSON.parse((el.querySelector('[data-new-ids]') || {}).getAttribute ? el.querySelector('[data-new-ids]').getAttribute('data-new-ids') : '[]');
                if (known !== null && ids.some(function (id) { return known.indexOf(id) === -1; }) && document.getElementById('kitchen-sound').checked) beep();
                known = ids;
            }
            check();
            setInterval(function () { if (!document.hidden) $.request('onRefresh', { complete: check }); }, 8000);
            $(document).on('ajaxComplete', function () { var el = document.querySelector('[data-new-ids]'); if (el) { try { known = known || JSON.parse(el.getAttribute('data-new-ids')); } catch (e) {} } });
        })();
        </script>
    <?php endif ?>
</div>
