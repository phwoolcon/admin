<?php
namespace Phwoolcon\Admin\Acl\Adapter;

use Phalcon\Acl\Adapter\Memory;
use Phwoolcon\Admin\Model\Acl\Resource;
use Phwoolcon\Db;
use Phwoolcon\Text;

class UrlAcl extends Memory
{
    protected $regexUrls = [];
    protected $normalUrls = [];

    public function addResourceAccess($resourceName, $accessList)
    {
        if (Text::startsWith($resourceName, 'url')) {
            $list = (array)$accessList;
            foreach ($list as $accessName) {
                if ($accessName{0} == '#') {
                    $this->regexUrls[$resourceName][$accessName] = $accessName;
                } else {
                    $this->normalUrls[$resourceName][$accessName] = $accessName;
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
        if (Text::startsWith($resourceName, 'url')) {
            $resourceName = strtolower($resourceName);
            if (!isset($this->normalUrls[$resourceName][$access])) {
                foreach (fnGet($this->regexUrls, $resourceName, []) as $pattern) {
                    if (preg_match($pattern, $access)) {
                        $access = $pattern;
                        break;
                    }
                }
            }
        }
        return parent::isAllowed($roleName, $resourceName, $access, $parameters);
    }
}
