<?php
namespace Phwoolcon\Admin\Model\Acl;

use Phalcon\Acl\Role as PhalconRole;
use Phwoolcon\Cache;
use Phwoolcon\Config;
use Phwoolcon\Model;
use Phwoolcon\Model\Config as ConfigModel;

/**
 * Class Role
 * @package Phwoolcon\Admin\Model\Acl
 *
 * @property string $description
 * @property string $name
 * @method string getDescription()
 * @method string getName()
 * @method $this setDescription(string $description)
 * @method $this setName(string $name)
 */
class Role extends Model
{
    const CACHE_KEY_ROLE_OPTIONS = 'admin-role-options';
    const CONFIG_KEY_DEFAULT_ROLE = 'admin.acl.default_role';

    protected $_table = 'admin_acl_roles';
    protected $_useDistributedId = false;

    public function isDefault()
    {
        return $this->getName() == Config::get(static::CONFIG_KEY_DEFAULT_ROLE);
    }

    public function toPhalconRole()
    {
        return new PhalconRole($this->name, $this->description);
    }

    protected function afterSave()
    {
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
