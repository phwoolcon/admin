<?php
namespace Phwoolcon\Admin\Model\Acl;

use Phwoolcon\Model;

/**
 * Class Grant
 * @package Phwoolcon\Admin\Model\Acl
 */
class Grant extends Model
{
    use \AdminAclGrantsModelTrait;

    protected $_table = 'admin_acl_grants';
    protected $_useDistributedId = false;
}
