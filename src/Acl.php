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
        'cache_time_key' => 'admin_acl_time',
        'local_cache_file' => 'admin-acl.php',
        'local_cache_time_file' => 'admin-acl-time.php',
        'superuser_role' => 'admin',
    ];
    /**
     * @var Di
     */
    protected static $di;
    protected static $localCacheFile;
    protected static $localCacheTimeFile;

    public static function __callStatic($name, $arguments)
    {
        static::$adapter === null and static::load();
        return call_user_func_array([static::$adapter, $name], $arguments);
    }

    protected static function clearCache()
    {
        Cache::delete(static::$config['cache_key']);
        Cache::delete(static::$config['cache_time_key']);
        static::clearLocalCache();
        static::$adapter = null;
    }

    protected static function clearLocalCache()
    {
        is_file($file = static::$localCacheFile) and unlink($file);
        is_file($file = static::$localCacheTimeFile) and unlink($file);
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

    /**
     * @param Role  $role
     * @param array $resources
     * @throws \Exception
     */
    public static function grant(Role $role, array $resources)
    {
        $di = static::$di;
        $roleId = $role->getId();
        /* @var Grant $grantClass */
        $grantClass = Grant::class;
        $di->has($grantClass) and $grantClass = $di->getRaw($grantClass);
        if ($resources) {
            if (!reset($resources) instanceof Resource) {
                $modelsManager = $role->getModelsManager();
                $builder = $modelsManager->createBuilder();
                $builder->from(Resource::class)->inWhere('id', $resources);
                $resources = $builder->getQuery()->execute();
            }
            $grants = [];
            foreach ($resources as $resource) {
                $resourceId = $resource->getId();
                $grants[$resourceId] = [
                    'role_id' => $roleId,
                    'resource_id' => $resourceId,
                ];
                foreach ($resource->getAliasIds() as $aliasId) {
                    $grants[$aliasId] = [
                        'role_id' => $roleId,
                        'resource_id' => $aliasId,
                    ];
                }
            }
            /* @var Grant $grantModel */
            $grantModel = new $grantClass;
            $db = Db::connection();
            $db->begin();
            try {
                $grantClass::findSimple(['role_id' => $roleId])->delete();
                foreach ($grants as $grant) {
                    $db->insertAsDict($grantModel->getSource(), $grant);
                }
                $db->commit();
            } catch (\Exception $e) {
                $db->rollback();
                throw $e;
            }
        } else {
            $grantClass::findSimple(['role_id' => $roleId])->delete();
        }
        static::clearCache();
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
        if (static::loadFromLocalCache()) {
            return true;
        }
        if (($adapter = Cache::get(static::$config['cache_key'])) instanceof PhalconAcl\AdapterInterface) {
            static::$adapter = $adapter;
            static::saveLocalCache();
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
        $adapter->setGeneratedAt((int)(microtime(true) * 1e3));

        // Load roles
        /* @var Role $roleClass */
        $roleClass = Role::class;
        $di->has($roleClass) and $roleClass = $di->getRaw($roleClass);
        /* @var Role[] $roles */
        if (!$roles = $roleClass::find()) {
            return false;
        }
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
        if (!$resources = $resourceClass::find()) {
            return false;
        }
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

    protected static function loadFromLocalCache()
    {
        // Check remote cache validity
        if (!$remoteCacheTime = Cache::get(static::$config['cache_time_key'])) {
            return false;
        }
        try {
            // Check local cache existence
            if (!is_file(static::$localCacheFile) || !is_file(static::$localCacheTimeFile)) {
                return false;
            }
            // Check local cache validity
            $localCacheTime = include static::$localCacheTimeFile;
            if ($localCacheTime == $remoteCacheTime && $cacheContent = include static::$localCacheFile) {
                if (($adapter = unserialize($cacheContent)) instanceof PhalconAcl\AdapterInterface) {
                    static::$adapter = $adapter;
                    return true;
                }
            }
        } catch (\Exception $e) {
            static::clearLocalCache();
        }
        return false;
    }

    public static function refreshCache()
    {
        static::clearCache();
        static::load();
    }

    public static function refreshDb()
    {
        $db = Db::connection();
        $db->begin();
        static::truncateResources();
        static::saveControllerResources();
        static::saveStaticResources();
        $db->commit();
        static::refreshCache();
    }

    public static function register(Di $di)
    {
        static::$di = $di;
        static::$config = array_merge(static::$config, Config::get('admin.acl'));
        static::$localCacheFile = storagePath('cache/' . static::$config['local_cache_file']);
        static::$localCacheTimeFile = storagePath('cache/' . static::$config['local_cache_time_file']);
        Events::attach('cache:after_clear', function (Event $event, $source) {
            try {
                $db = Db::connection();
            } catch (\Exception $e) {
                return;
            }
            $resourceReflection = new ReflectionClass(Resource::class);
            $properties = $resourceReflection->getDefaultProperties();
            if (!$db->tableExists(fnGet($properties, '_table'))) {
                return;
            }
            static::refreshDb();
            if ($source instanceof Command) {
                $source->info('ACL refreshed.');
            }
        });
    }

    protected static function saveCache()
    {
        Cache::set(static::$config['cache_key'], static::$adapter);
        Cache::set(static::$config['cache_time_key'], static::$adapter->getGeneratedAt());
        static::saveLocalCache();
    }

    protected static function saveLocalCache()
    {
        $adapter = static::$adapter;
        fileSaveArray(static::$localCacheTimeFile, $adapter->getGeneratedAt());
        fileSaveArray(static::$localCacheFile, serialize($adapter));
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
                ],
            ];

            $aliasData = array_merge($data, [
                'access' => $route->getCompiledPattern(),
                'is_alias' => 1,
            ]);
            $aliasIds = [];
            // Save alias resource
            foreach ((array)$route->getHttpMethods() as $httpMethod) {
                $aliasData['resource'] = 'url-' . strtolower($httpMethod);
                $aliasId = md5($aliasData['resource'] . '|' . $aliasData['access']);
                $aliasIds[] = $aliasData['id'] = $aliasId;
                if (!$aliasResource = Resource::findFirstSimple(['id' => $aliasId])) {
                    $aliasResource = new Resource();
                }
                $aliasResource->addData($aliasData);
                $aliasResource->save();
            }

            // Save resource
            $data['details']['alias_ids'] = $aliasIds;
            if (!$resource = Resource::findFirstSimple(['id' => $id])) {
                $resource = new Resource();
            }
            $resource->addData($data);
            $resource->save();
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
