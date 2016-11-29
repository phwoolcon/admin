<?php
namespace Phwoolcon\Admin\Model\Acl;

use Phwoolcon\Model;

/**
 * Class Grant
 * @package Phwoolcon\Admin\Model\Acl
 *
 * @method string getResourceId()
 * @method string getRoleId()
 */
class Grant extends Model
{
    protected $_table = 'admin_acl_grants';
    protected $_useDistributedId = false;
}
