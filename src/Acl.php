<?php
namespace Phwoolcon\Admin;

use Phalcon\Acl as PhalconAcl;
use Phalcon\Di;
use Phalcon\Events\Event;
use Phalcon\Mvc\Router\Route;
use Phwoolcon\Admin\Acl\Adapter\NoAcl;
use Phwoolcon\Admin\Acl\Adapter\UrlAcl;
use Phwoolcon\Admin\Model\Acl\Grant;
use Phwoolcon\Admin\Model\Acl\Resource;
use Phwoolcon\Admin\Model\Acl\Role;
use Phwoolcon\Admin\Model\Admin;
use Phwoolcon\Cache;
use Phwoolcon\Cli\Command;
use Phwoolcon\Config;
use Phwoolcon\Db;
use Phwoolcon\Events;
use Phwoolcon\Router;
use Phwoolcon\Text;
use ReflectionClass;

/**
 * Class Acl
 * @package Phwoolcon\Admin
 */
class Acl extends PhalconAcl
{
    /**
     * @var UrlAcl
     */
    protected static $adapter;
    protected static $config = [
        'cache_key' => 'admin_acl_adapter',
        'superuser_role' => 'admin',
    ];
    /**
     * @var Di
     */
    protected static $di;

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

    protected static function getDocTagValue($phpDoc, $tag)
    {
        // Don't need to compare with false because strpos will never return 0 in this case
        if (!$startPos = strpos($phpDoc, '* @' . $tag)) {
            return null;
        }
        $startPos += strlen($tag) + 4;
        if (!$endPos = strpos($phpDoc, "\n", $startPos)) {
            return null;
        }
        return trim(substr($phpDoc, $startPos, $endPos - $startPos));
    }

    /**
     * @return Route[]
     */
    protected static function getRoutes()
    {
        $di = static::$di;
        /* @var Router $router */
        $router = $di->getShared('router');
        /* @var Route[] $routes */
        $routes = $router->getRoutes();
        return $routes;
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
        $class = UrlAcl::class;
        $di = static::$di;
        $di->has($class) and $class = $di->getRaw($class);

        /* @var UrlAcl $adapter */
        $adapter = new $class;
        $adapter->setDefaultAction(static::DENY);

        // Load roles
        /* @var Role $roleClass */
        $roleClass = Role::class;
        $di->has($roleClass) and $roleClass = $di->getRaw($roleClass);
        /* @var Role[] $roles */
        $roles = $roleClass::find();
        $rolesMap = [];
        foreach ($roles as $role) {
            $adapter->addRole($role->toPhalconRole());
            $rolesMap[$role->getId()] = $role;
        }

        // Load resources
        /* @var \Phwoolcon\Admin\Model\Acl\Resource $resourceClass */
        $resourceClass = Resource::class;
        $di->has($resourceClass) and $resourceClass = $di->getRaw($resourceClass);
        /* @var \Phwoolcon\Admin\Model\Acl\Resource[] $resources */
        $resources = $resourceClass::find();
        $resourcesMap = [];
        foreach ($resources as $resource) {
            $adapter->addResource($resource->getResource(), $resource->getAccess());
            $resourcesMap[$resource->getId()] = $resource;
        }
        // Load grants
        /* @var Grant $grantClass */
        $grantClass = Grant::class;
        $di->has($grantClass) and $grantClass = $di->getRaw($grantClass);
        /* @var Grant[] $grants */
        $grants = $grantClass::find();
        foreach ($grants as $grant) {
            $roleId = $grant->getRoleId();
            $resourceId = $grant->getResourceId();
            /* @var Role $grantRole */
            /* @var \Phwoolcon\Admin\Model\Acl\Resource $grantResource */
            if (($grantRole = fnGet($rolesMap, $roleId)) && ($grantResource = fnGet($resourcesMap, $resourceId))) {
                $adapter->allow($grantRole->getName(), $grantResource->getResource(), $grantResource->getAccess());
            }
        }

        static::$adapter = $adapter;
        return true;
    }

    public static function refreshCache()
    {
        static::clearCache();
        static::load();
    }

    public static function refreshDb()
    {
        static::truncateResources();
        static::saveControllerResources();
        static::saveStaticResources();
        static::refreshCache();
    }

    public static function saveGrants()
    {
    }

    public static function register(Di $di)
    {
        static::$di = $di;
        static::$config = Config::get('admin.acl');
        Events::attach('cache:after_clear', function (Event $event, $source) {
            static::refreshDb();
            if ($source instanceof Command) {
                $source->info('ACL refreshed.');
            }
        });
    }

    protected static function saveCache()
    {
        Cache::set(static::$config['cache_key'], static::$adapter);
    }

    protected static function saveControllerResources()
    {
        $prefix = '/admin';

        /* @var ReflectionClass[] $foundClasses */
        $foundClasses = [];
        foreach (static::getRoutes() as $route) {
            // Check admin routes only
            if (!Text::startsWith($pattern = $route->getPattern(), $prefix)) {
                continue;
            }
            // Parse controller and method from route
            $path = $route->getPaths();
            $controller = $path['controller'];
            $method = $path['action'];
            if (!method_exists($controller, $method)) {
                continue;
            }

            $reflection = isset($foundClasses[$controller]) ? $foundClasses[$controller] :
                ($foundClasses[$controller] = new ReflectionClass($controller));

            // Skip controllers and methods
            $properties = $reflection->getDefaultProperties();
            if (fnGet($properties, 'skipAcl')) {
                continue;
            }
            if (fnGet($properties, 'skipAclMethod.' . $method)) {
                continue;
            }

            // Parse resource name and access name
            $resourceName = $controller;
            if ($controllerDoc = $reflection->getDocComment()) {
                if ($tagValue = static::getDocTagValue($controllerDoc, 'acl-name')) {
                    $resourceName = $tagValue;
                } elseif (preg_match('/\*\*.*?\*(.*?)\@/is', $controllerDoc, $match)) {
                    $resourceName = trim(str_replace(["*", "\n", "\r", " "], '', $match[1]), '*');
                }
            }
            $accessName = $method;
            $reflectionMethod = $reflection->getMethod($method);
            if ($methodDoc = $reflectionMethod->getDocComment()) {
                if ($tagValue = static::getDocTagValue($methodDoc, 'acl-name')) {
                    $accessName = $tagValue;
                } elseif (preg_match('/\*\*.*?\*(.*?)\@/is', $methodDoc, $match)) {
                    $accessName = trim(str_replace(["*", "\n", "\r", " "], '', $match[1]), '*');
                }
            }
            $data = [
                'id' => $id = md5($controller . '|' . $method),
                'resource' => $controller,
                'access' => $method,
                'is_alias' => 0,
                'details' => [
                    'resource_name' => $resourceName,
                    'access_name' => $accessName,
                    'pattern' => $pattern,
                    'alias' => $aliasId = 'alias_' . $id,
                ],
            ];
            $aliasData = array_merge($data, [
                'id' => $aliasId,
                'resource' => 'url',
                'access' => $route->getCompiledPattern(),
                'is_alias' => 1,
            ]);

            // Save resource
            if (!$resource = Resource::findFirstSimple(['id' => $id])) {
                $resource = new Resource();
            }
            $resource->addData($data);
            $resource->save();

            // Save alias resource
            if (!$aliasResource = Resource::findFirstSimple(['id' => $aliasId])) {
                $aliasResource = new Resource();
            }
            $aliasResource->addData($aliasData);
            $aliasResource->save();
        }
    }

    protected static function saveStaticResources()
    {
    }

    protected static function truncateResources()
    {
        Db::connection()->delete((new Resource())->getSource());
    }
}
