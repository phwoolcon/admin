<?php
namespace Phwoolcon\Admin\Model;

use Phwoolcon\Admin\Model\Acl\Role;
use Phwoolcon\Config;
use Phwoolcon\Model;

/**
 * Class Admin
 * @package Phwoolcon\Admin\Model
 *
 * @property array  $brief_roles
 * @property Role[]|\Phalcon\Mvc\Model\Resultset\Simple $roles
 *
 * @method string getStatus()
 */
class Admin extends Model
{
    const STATUS_NORMAL = 'normal';
    const STATUS_BANNED = 'banned';

    protected static $statusLabels = [
        self::STATUS_NORMAL => '正常',
        self::STATUS_BANNED => '禁用',
    ];
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

    public function getStatusLabel($status = null)
    {
        $status === null and $status = $this->getStatus();
        return isset(static::$statusLabels[$status]) ? __(static::$statusLabels[$status]) : '';
    }

    public static function getStatuses()
    {
        return static::$statusLabels;
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
        if ($this->_isNew && !count($this->roles)) {
            $roleModel = Role::class;
            if ($this->_dependencyInjector->has($roleModel)) {
                $roleModel = $this->_dependencyInjector->getRaw($roleModel);
            }
            /* @var Role $roleModel */
            $roleName = Config::get(static::findFirst() ? 'admin.acl.default_role' : 'admin.acl.superuser_role');
            $defaultRole = $roleModel::findFirstSimple(['name' => $roleName]);
            $this->roles = [$defaultRole];
            $this->_related['roles'] = [$defaultRole];
        }
        if (count($this->roles)) {
            foreach ($this->roles as $role) {
                $briefRoles[$role->getName()] = $role->getDescription();
            }
        }
        $this->brief_roles = $briefRoles;
        parent::prepareSave();
    }
}
