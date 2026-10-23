<?php namespace Aero\Church\Controllers;

use Aero\Church\Classes\GroupAccess;
use Aero\Church\Models\Group;
use Aero\Church\Models\GroupMember;
use Aero\Church\Models\Member;
use Backend\Classes\Controller;
use BackendMenu;

class Groups extends Controller
{
    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $requiredPermissions = ['aero.church.use', 'aero.church.pastor', 'aero.church.secretary', 'aero.church.leader', 'aero.church.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Church', 'church', 'groups');
    }

    public function listExtendQuery($query): void
    {
        $query->whereIn('aero_church_groups.id', Group::managed()->select('aero_church_groups.id'));
    }

    public function formExtendQuery($query): void
    {
        $this->listExtendQuery($query);
    }

    /** Un líder administra sus grupos; crear grupos nuevos es de la administración. */
    public function create($context = null)
    {
        if (GroupAccess::restrictedUserId() !== null) {
            abort(403);
        }

        return $this->asExtension('FormController')->create($context);
    }

    public function update_onAddMember($recordId = null)
    {
        $group = Group::managed()->findOrFail((int) $recordId);
        $memberId = (int) post('member_id');
        $role = in_array(post('role'), array_keys(GroupMember::ROLES), true) ? post('role') : 'member';

        if (!Member::visible()->whereKey($memberId)->exists()) {
            throw new \ApplicationException('Elige un miembro de la lista.');
        }

        $gm = GroupMember::firstOrNew(['group_id' => $group->id, 'member_id' => $memberId]);
        $gm->tenant_id = $group->tenant_id;
        $gm->role = $role;
        $gm->joined_at = $gm->joined_at ?: now()->toDateString();
        $gm->left_at = null;
        $gm->save();

        \Flash::success('Integrante agregado.');
        return \Redirect::refresh();
    }

    public function update_onRemoveMember($recordId = null)
    {
        $group = Group::managed()->findOrFail((int) $recordId);
        GroupMember::where('group_id', $group->id)->where('id', (int) post('id'))->update(['left_at' => now()->toDateString()]);

        \Flash::success('Integrante dado de baja del grupo.');
        return \Redirect::refresh();
    }
}
