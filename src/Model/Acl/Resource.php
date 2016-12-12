<?php
namespace Phwoolcon\Admin\Model\Acl;

use Phwoolcon\Model;

/**
 * Class Resource
 * @package Phwoolcon\Admin\Model\Acl
 */
class Resource extends Model
{
    use \AdminAclResourcesModelTrait;

    protected $_table = 'admin_acl_resources';
    protected $_useDistributedId = false;

    protected $_jsonFields = ['details'];

    public function getAliasIds()
    {
        $details = $this->getDetails();
        return isset($details['alias_ids']) ? $details['alias_ids'] : [];
    }
}
