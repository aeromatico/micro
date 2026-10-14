<div data-control="toolbar">
    <a href="<?= Backend::url('aero/community/posts/create') ?>" class="btn btn-primary oc-icon-plus">Nueva publicación</a>
    <?php if (!empty(request('type')) || request('mine') || request('pending')): ?>
        <a href="<?= Backend::url('aero/community/posts') ?>" class="btn btn-default">Ver todo el foro</a>
    <?php endif ?>
</div>
