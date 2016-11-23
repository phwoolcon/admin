<?php
namespace Phwoolcon\Admin\Model\Acl;

use Phwoolcon\Model;

/**
 * Class Role
 * @package Phwoolcon\Admin\Model\Acl
 *
 * @method string getDescription()
 * @method string getName()
 */
class Role extends Model
{
    protected $_table = 'admin_acl_roles';
    protected $_useDistributedId = false;
}
