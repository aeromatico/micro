/* Mis módulos (aero/sites): switches en vivo y bienvenida. Usa el framework AJAX del backend ($.request). */
(function () {
    function init() {
        var page = document.querySelector('.mm-page');
        if (!page || page.dataset.mmReady) return;
        page.dataset.mmReady = '1';
        var live = page.dataset.mmMode === 'live';
        var cards = function () { return Array.prototype.slice.call(page.querySelectorAll('.mm-card')); };
        var byCode = function (code) { return page.querySelector('.mm-card[data-code="' + code + '"]'); };

        function setCard(card, on) {
            if (!card) return;
            var input = card.querySelector('input');
            if (input.disabled) return;
            input.checked = on;
            card.classList.toggle('is-on', on);
        }

        function updateCount() {
            var el = page.querySelector('[data-mm-count]');
            if (el) el.textContent = page.querySelectorAll('.mm-card input:checked').length;
        }

        // En la bienvenida, las dependencias se marcan/desmarcan en el navegador.
        function cascade(code, on) {
            if (on) {
                var card = byCode(code);
                (card && card.dataset.requires ? card.dataset.requires.split(',') : []).forEach(function (req) {
                    var c = byCode(req);
                    if (c && !c.querySelector('input').checked) { setCard(c, true); cascade(req, true); }
                });
            } else {
                cards().forEach(function (c) {
                    var reqs = c.dataset.requires ? c.dataset.requires.split(',') : [];
                    if (reqs.indexOf(code) !== -1 && c.querySelector('input').checked) { setCard(c, false); cascade(c.dataset.code, false); }
                });
            }
        }

        function toggleLive(card, on) {
            var input = card.querySelector('input');
            card.classList.add('is-busy');
            var req = $.request('onToggle', {
                data: { code: card.dataset.code, enabled: on ? 1 : 0 },
                loading: false,
                success: function (data) {
                    Object.keys(data.states || {}).forEach(function (code) { setCard(byCode(code), data.states[code]); });
                    var box = page.querySelector('[data-mm-saved]');
                    if (box) { box.hidden = false; box.querySelector('[data-mm-saved-text]').textContent = data.message; }
                    var notice = page.querySelector('.mm-notice');
                    if (notice && on) notice.remove();
                    applyFilter();
                }
            });
            var revert = function () { input.checked = !on; card.classList.toggle('is-on', !on); };
            if (req && req.fail) { req.fail(revert); }
            if (req && req.always) { req.always(function () { card.classList.remove('is-busy'); }); }
            else { card.classList.remove('is-busy'); }
        }

        page.addEventListener('change', function (e) {
            var input = e.target;
            if (!input.matches('.mm-switch input')) return;
            var card = input.closest('.mm-card');
            card.classList.toggle('is-on', input.checked);
            if (live) {
                toggleLive(card, input.checked);
            } else {
                cascade(card.dataset.code, input.checked);
                updateCount();
            }
        });

        // Clic en «Mejorar plan» no debe activar el label.
        page.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-mm-enable]');
            if (btn) {
                var card = byCode(btn.dataset.mmEnable);
                if (card) { setCard(card, true); toggleLive(card, true); card.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
            }
        });

        // Búsqueda y filtro (solo pantalla en vivo).
        var filter = 'all';
        function applyFilter() {
            var search = page.querySelector('[data-mm-search]');
            var q = search ? search.value.trim().toLowerCase() : '';
            cards().forEach(function (c) {
                var on = c.querySelector('input').checked;
                var ok = (!q || c.dataset.name.indexOf(q) !== -1)
                    && (filter === 'all' || (filter === 'on' && on) || (filter === 'off' && !on));
                c.hidden = !ok;
            });
            page.querySelectorAll('.mm-group').forEach(function (g) {
                g.hidden = !g.querySelector('.mm-card:not([hidden])');
            });
        }
        var search = page.querySelector('[data-mm-search]');
        if (search) search.addEventListener('input', applyFilter);
        var chips = page.querySelector('[data-mm-filter]');
        if (chips) chips.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            filter = b.dataset.filter;
            chips.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-active', x === b); });
            applyFilter();
        });

        // Bienvenida: guardar.
        function finish(skip) {
            var codes = Array.prototype.map.call(page.querySelectorAll('.mm-card input:checked'), function (i) { return i.value; });
            $.request('onFinishWelcome', { data: skip ? { skip: 1 } : { codes: codes } });
        }
        var fin = page.querySelector('[data-mm-finish]');
        if (fin) fin.addEventListener('click', function () { finish(false); });
        var skip = page.querySelector('[data-mm-skip]');
        if (skip) skip.addEventListener('click', function () { finish(true); });

        updateCount();
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
    document.addEventListener('render', init);
})();
