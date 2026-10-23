<?php if (\Aero\Church\Classes\GroupAccess::restrictedUserId() === null): ?>
<div data-control="toolbar">
    <a href="<?= Backend::url('aero/church/groups/create') ?>" class="btn btn-primary oc-icon-plus">Nuevo</a>
</div>
<?php endif ?>
