<?php
namespace Phwoolcon\Admin\Model;

use Phwoolcon\Admin\Model\Acl\Role;
use Phwoolcon\Model;

/**
 * Class Admin
 * @package Phwoolcon\Admin\Model
 *
 * @property array  $brief_roles
 * @property Role[] $roles
 */
class Admin extends Model
{
    protected $_table = 'admins';
    protected $_useDistributedId = false;
    protected $_jsonFields = ['brief_roles'];

    public function getAclResources()
    {
    }

    public function getBriefRoles()
    {
        return $this->brief_roles ?: [];
    }

    public function initialize()
    {
        parent::initialize();
        $this->hasManyToMany('id', AdminRole::class, 'admin_id', 'role_id', Role::class, 'id', [
            'alias' => 'roles',
        ]);
    }

    protected function prepareSave()
    {
        $briefRoles = [];
        if ($this->roles) {
            foreach ($this->roles as $role) {
                $briefRoles[$role->getName()] = $role->getDescription();
            }
        }
        $this->brief_roles = $briefRoles;
        parent::prepareSave();
    }
}
