<?php
namespace Phwoolcon\Admin\Model;

use Phwoolcon\Admin\Model\Acl\Role;
use Phwoolcon\Config;
use Phwoolcon\Model;

/**
 * Class Admin
 * @package Phwoolcon\Admin\Model
 *
 * @property array                                      $brief_roles
 * @property Role[]|\Phalcon\Mvc\Model\Resultset\Simple $roles
 *
 * @method Role[]|\Phalcon\Mvc\Model\Resultset\Simple getRoles()
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

    public function hasRole($roleName)
    {
        return isset($this->brief_roles[$roleName]);
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
        // Assign default role if no roles assigned
        if (!count($this->roles)) {

            $configKey = Role::CONFIG_KEY_DEFAULT_ROLE;
            // Assign superuser role for first admin
            if ($this->_isNew && !static::findFirst()) {
                $configKey = Role::CONFIG_KEY_SUPERUSER_ROLE;
            }
            $roleName = Config::get($configKey);

            /* @var Role $roleModel */
            $roleModel = $this->getInjectedClass(Role::class);
            $defaultRole = $roleModel::findFirstSimple(['name' => $roleName]);
            $this->roles = [$defaultRole];
            $this->_related['roles'] = [$defaultRole];
        }

        // Update brief roles
        $briefRoles = [];
        if (count($this->roles)) {
            foreach ($this->roles as $role) {
                $briefRoles[$role->getName()] = $role->getDescription();
            }
        }
        $this->brief_roles = $briefRoles;
        parent::prepareSave();
    }
}
