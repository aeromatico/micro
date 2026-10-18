/* Arrastrar para reordenar los atajos del dashboard (delegado: el widget llega por AJAX). */
(function () {
    var dragged = null;

    function row(el) { return el && el.closest ? el.closest('[data-aero-sc]') : null; }
    function item(el) { return el && el.closest ? el.closest('.aero-sc-item[data-id]') : null; }

    // En modo edición el widget cancela los clics (preventDefault en burbuja); en captura
    // los cortamos antes para que los enlaces del widget naveguen igual.
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('[data-aero-sc] a[href]') : null;
        if (a) e.stopPropagation();
    }, true);

    document.addEventListener('dragstart', function (e) {
        var it = item(e.target);
        if (!it) return;
        // El dashboard cancela dragstart en un contenedor (stop+prevent): se atiende antes, en captura.
        e.stopPropagation();
        dragged = it;
        it.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', it.dataset.id); } catch (err) {}
    }, true);

    document.addEventListener('dragover', function (e) {
        var it = item(e.target);
        if (!dragged || !it || it === dragged || row(it) !== row(dragged)) return;
        e.preventDefault();
        e.stopPropagation();
        var rect = it.getBoundingClientRect();
        var after = e.clientX > rect.left + rect.width / 2;
        it.parentNode.insertBefore(dragged, after ? it.nextSibling : it);
    }, true);

    document.addEventListener('dragend', function () {
        if (!dragged) return;
        var r = row(dragged);
        dragged.classList.remove('is-dragging');
        dragged = null;
        if (!r) return;

        var ids = Array.prototype.map.call(r.querySelectorAll('.aero-sc-item[data-id]'), function (n) { return n.dataset.id; });
        var meta = document.querySelector('meta[name="csrf-token"]');
        var body = new URLSearchParams();
        ids.forEach(function (id) { body.append('ids[]', id); });

        fetch(r.dataset.reorderUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-OCTOBER-REQUEST-HANDLER': 'onReorder',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': meta ? meta.content : '',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: body.toString()
        });
    }, true);
})();
