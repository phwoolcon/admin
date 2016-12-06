<?php
namespace Phwoolcon\Admin\Model;

use Phwoolcon\Admin\Model\Acl\Role;
use Phwoolcon\Model;

/**
 * Class AdminRole
 * @package Phwoolcon\Admin\Model
 *
 * @property Admin $admin
 * @property Role $role
 * @method Admin getAdmin()
 * @method Role getRole()
 */
class AdminRole extends Model
{
    protected $_table = 'admin_roles';
    protected $_useDistributedId = false;

    public function initialize()
    {
        parent::initialize();
        $this->hasOne('admin_id', Admin::class, 'id', ['alias' => 'admin']);
        $this->hasOne('role_id', Role::class, 'id', ['alias' => 'role']);
    }
}
