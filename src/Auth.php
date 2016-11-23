<?php

namespace Phwoolcon\Admin;

use Phalcon\Di;
use Phalcon\Security;
use Phwoolcon\Admin\Auth\Adapter\Generic;
use Phwoolcon\Admin\Model\Admin;
use Phwoolcon\Auth\Adapter\Exception;
use Phwoolcon\Auth\AdapterInterface;
use Phwoolcon\Auth\Auth as PhwoolconAuth;
use Phwoolcon\Config;

/**
 * Class Admin
 * @package Phwoolcon\Admin
 *
 * @property Generic $instance
 * @method static false|Admin getUser
 */
class Auth extends PhwoolconAuth
{

    public static function register(Di $di)
    {
        static::$di = $di;
        static::$config = Config::get('admin.auth');
        $di->setShared('admin', function () {
            $di = static::$di;
            $config = static::$config;
            $class = $config['adapter'];
            $options = $config['options'];
            strpos($class, '\\') === false and $class = 'Phwoolcon\\Admin\\Auth\\Adapter\\' . $class;
            if ($di->has($class)) {
                $class = $di->getRaw($class);
            }
            if (!class_exists($class)) {
                throw new Exception('Admin auth adapter class not found, please check config file admin.php');
            }
            /* @var Security $hasher */
            $hasher = static::$di->getShared('security');
            $hasher->setDefaultHash($options['security']['default_hash']);
            $hasher->setWorkFactor($options['security']['work_factor']);
            $adapter = new $class($options, $hasher);
            if (!$adapter instanceof AdapterInterface) {
                throw new Exception('Admin auth adapter class should implement ' . AdapterInterface::class);
            }
            $adapter->setDi($di);
            return $adapter;
        });
    }
}
