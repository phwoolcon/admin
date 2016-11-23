<?php
namespace Phwoolcon\Admin\Acl\Adapter;

use Phalcon\Acl\Adapter\Memory;
use Phwoolcon\Log;

class NoAcl extends Memory
{

    public function __construct()
    {
        Log::warning(__('ACL rules not defined, all accesses will be granted to all admin users.'));
    }

    public function isAllowed($roleName, $resourceName, $access, array $parameters = null)
    {
        return true;
    }
}
