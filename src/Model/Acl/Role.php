<?php
namespace Phwoolcon\Admin\Model\Acl;

use Phalcon\Acl\Role as PhalconRole;
use Phwoolcon\Cache;
use Phwoolcon\Model;

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

    protected $_table = 'admin_acl_roles';
    protected $_useDistributedId = false;

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
}
