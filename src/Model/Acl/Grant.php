<?php
namespace Phwoolcon\Admin\Model\Acl;

use Phwoolcon\Model;

class Grant extends Model
{
    protected $_table = 'admin_acl_grants';
    protected $_useDistributedId = false;
}
