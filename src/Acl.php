<?php
namespace Phwoolcon\Admin;

use Phalcon\Acl as PhalconAcl;
use Phalcon\Acl\Adapter\Memory;
use Phalcon\Di;
use Phwoolcon\Admin\Acl\Adapter\NoAcl;
use Phwoolcon\Admin\Model\Admin;
use Phwoolcon\Cache;
use Phwoolcon\Config;

/**
 * Class Acl
 * @package Phwoolcon\Admin
 *
 * @uses    Memory::isAllowed()
 */
class Acl extends PhalconAcl
{
    /**
     * @var Di
     */
    protected static $di;
    protected static $config = [
        'cache_key' => 'admin_acl_adapter',
        'superuser_role' => 'admin',
    ];

    /**
     * @var Memory
     */
    protected static $adapter;

    public static function __callStatic($name, $arguments)
    {
        static::$adapter === null and static::load();
        return call_user_func_array([static::$adapter, $name], $arguments);
    }

    protected static function clearCache()
    {
        Cache::delete(static::$config['cache_key']);
    }

    public static function load()
    {
        if (!static::loadFromCache()) {
            if (static::loadFromDb()) {
                static::saveCache();
            } else {
                static::$adapter = new NoAcl();
            }
        }
    }

    protected static function loadFromCache()
    {
        if (($adapter = Cache::get(static::$config['cache_key'])) instanceof PhalconAcl\AdapterInterface) {
            static::$adapter = $adapter;
            return true;
        }
        return false;
    }

    protected static function loadFromDb()
    {
        $adapter = new Memory();
        $adapter->setDefaultAction(static::DENY);
        static::$adapter = $adapter;
        return true;
    }

    public static function refreshCache()
    {
        static::clearCache();
        static::load();
    }

    public static function register(Di $di)
    {
        static::$di = $di;
        static::$config = Config::get('admin.acl');
    }

    protected static function saveCache()
    {
        Cache::set(static::$config['cache_key'], static::$adapter);
    }

    public static function isAllowed(Admin $user, $resourceName, $access, array $parameters = null)
    {
        static::$adapter === null and static::load();
        $roles = $user->getBriefRoles();
        if (isset($roles[static::$config['superuser_role']])) {
            return true;
        }
        foreach ($roles as $roleName => $description) {
            if (static::$adapter->isAllowed($roleName, $resourceName, $access, $parameters)) {
                return true;
            }
        }
        return false;
    }
}
