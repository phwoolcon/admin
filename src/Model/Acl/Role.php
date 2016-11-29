<?php
namespace Phwoolcon\Admin\Model\Acl;

use Phalcon\Acl\Role as PhalconRole;
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
    protected $_table = 'admin_acl_roles';
    protected $_useDistributedId = false;

    public function toPhalconRole()
    {
        return new PhalconRole($this->name, $this->description);
    }
}
