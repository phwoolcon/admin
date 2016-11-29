<?php
namespace Phwoolcon\Admin\Acl\Adapter;

use Phalcon\Acl\Adapter\Memory;
use Phwoolcon\Admin\Model\Acl\Resource;
use Phwoolcon\Db;

class UrlAcl extends Memory
{
    protected $regexUrls = [];
    protected $normalUrls = [];

    public function addResourceAccess($resourceName, $accessList)
    {
        if ($resourceName == 'url') {
            $list = (array)$accessList;
            foreach ($list as $accessName) {
                if ($accessName{0} == '#') {
                    $this->regexUrls[$accessName] = $accessName;
                } else {
                    $this->normalUrls[$accessName] = $accessName;
                }
            }
        }
        return parent::addResourceAccess($resourceName, $accessList);
    }

    public function getAccess()
    {
        return $this->_access;
    }

    public function getAccessList()
    {
        return $this->_accessList;
    }

    public function isAllowed($roleName, $resourceName, $access, array $parameters = null)
    {
        if ($resourceName == 'url' && !isset($this->normalUrls[$access])) {
            foreach ($this->regexUrls as $pattern) {
                if (preg_match($pattern, $access)) {
                    $access = $pattern;
                    break;
                }
            }
        }
        return parent::isAllowed($roleName, $resourceName, $access, $parameters);
    }
}
