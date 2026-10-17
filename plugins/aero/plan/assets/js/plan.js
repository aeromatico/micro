/* Plan de 30 días: autoguardado del dueño y repintado al cambiar el rubro. */
(function () {
    var timer = null;

    function form() { return document.getElementById('plan-form'); }

    function isClientField(el) { return el && el.name && el.name.indexOf('a[') === 0 && !el.disabled; }

    function autosave(el) {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var f = form();
            if (f && window.jQuery) { window.jQuery(f).request('onAutosave', { loading: false, flash: false }); }
        }, 900);
    }

    document.addEventListener('input', function (e) {
        if (isClientField(e.target) && !e.target.hasAttribute('data-plan-rubro')) { autosave(e.target); }
    });

    document.addEventListener('change', function (e) {
        var el = e.target;
        if (!isClientField(el)) { return; }
        if (el.hasAttribute('data-plan-rubro')) {
            clearTimeout(timer);
            window.jQuery(form()).request('onRubro', { flash: false });
        } else {
            autosave(el);
        }
    });
})();
