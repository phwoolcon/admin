<?php
namespace Phwoolcon\Admin\Model\Acl;

use Phalcon\Acl\Role as PhalconRole;
use Phwoolcon\Admin\Model\Admin;
use Phwoolcon\Admin\Model\AdminRole;
use Phwoolcon\Cache;
use Phwoolcon\Config;
use Phwoolcon\Model;
use Phwoolcon\Model\Config as ConfigModel;

/**
 * Class Role
 * @package Phwoolcon\Admin\Model\Acl
 *
 * @property AdminRole[] $admin_roles
 * @property Grant[]     $grants
 */
class Role extends Model
{
    use \AdminAclRolesModelTrait;

    const CACHE_KEY_ROLE_OPTIONS = 'admin-role-options';
    const CONFIG_KEY_DEFAULT_ROLE = 'admin.acl.default_role';
    const CONFIG_KEY_SUPERUSER_ROLE = 'admin.acl.superuser_role';

    /**
     * @var static[]
     */
    protected static $specialRoles = [];

    protected $_table = 'admin_acl_roles';
    protected $_useDistributedId = false;

    public function beforeDelete()
    {
        $affectedAdmins = [];
        foreach ($this->admin_roles as $adminRole) {
            if ($admin = $adminRole->getAdmin()) {
                $affectedAdmins[] = $admin;
            }
        }
        $this->_additionalData['delete_affected_admins'] = $affectedAdmins;
        return true;
    }

    public function afterDelete()
    {
        // Delete ACL grants
        foreach ($this->grants as $grant) {
            $grant->delete();
        }
        AdminRole::findSimple(['role_id' => $this->getId()])->delete();

        // Delete admin assignations
        /* @var Admin $admin */
        foreach ($this->_additionalData['delete_affected_admins'] as $admin) {
            $admin->save();
        }
        unset($this->_additionalData['delete_affected_admins']);
        Cache::delete(static::CACHE_KEY_ROLE_OPTIONS);
    }

    public function canDelete()
    {
        return !$this->isDefault() && !$this->isSuperuser();
    }

    public static function getDefaultRole()
    {
        if (!isset(static::$specialRoles['default'])) {
            static::$specialRoles['default'] = static::findFirstSimple([
                'name' => Config::get(static::CONFIG_KEY_DEFAULT_ROLE),
            ]);
        }
        return static::$specialRoles['default'];
    }

    public static function getSuperuserRole()
    {
        if (!isset(static::$specialRoles['superuser'])) {
            static::$specialRoles['superuser'] = static::findFirstSimple([
                'name' => Config::get(static::CONFIG_KEY_SUPERUSER_ROLE),
            ]);
        }
        return static::$specialRoles['superuser'];
    }

    public function initialize()
    {
        parent::initialize();
        $this->hasMany('id', Grant::class, 'role_id', ['alias' => 'grants']);
        $this->hasMany('id', AdminRole::class, 'role_id', ['alias' => 'admin_roles']);
    }

    public function isDefault($name = null)
    {
        $name === null and $name = $this->getName();
        return $name == Config::get(static::CONFIG_KEY_DEFAULT_ROLE);
    }

    public function isSuperuser($name = null)
    {
        $name === null and $name = $this->getName();
        return $name == Config::get(static::CONFIG_KEY_SUPERUSER_ROLE);
    }

    public function toPhalconRole()
    {
        return new PhalconRole($this->name, $this->description);
    }

    protected function afterSave()
    {
        if (!$this->_isNew) {
            // Update superuser role name in config, if name changed
            $originName = fnGet($this->_snapshot, 'name');
            $currentName = $this->getName();
            if ($originName != $currentName && $this->isSuperuser($originName)) {
                ConfigModel::saveConfig(static::CONFIG_KEY_SUPERUSER_ROLE, $currentName);
            }
        }
        parent::afterSave();
        Cache::delete(static::CACHE_KEY_ROLE_OPTIONS);
    }

    public static function getSelectOptions()
    {
        if (!$options = Cache::get($cacheKey = static::CACHE_KEY_ROLE_OPTIONS)) {
            $options = [];
            /* @var static $role */
            foreach (static::find() as $role) {
                $options[$role->getId()] = $role->getDescription();
            }
            Cache::set($cacheKey, $options);
        }
        return $options;
    }

    public function setAsDefault()
    {
        if ($name = $this->getName()) {
            ConfigModel::saveConfig(static::CONFIG_KEY_DEFAULT_ROLE, $name);
            return true;
        }
        return false;
    }
}
