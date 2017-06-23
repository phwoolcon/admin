# Phwoolcon Admin
Admin module for Phwoolcon

## Features

* Admin User and Roles model
* Admin Login Auth
* Admin ACL (route based)
* Admin operation log

## ACL HOWTOs
### How/Where to define ACL resources
The ACl component will scan `admin/*` routes for corresponding controller methods as resources, and looking for `@acl-name` annotation as display name.

To skip ACL check for a whole controller, please set property `$skipAcl` as `true`.

To skip ACL check for a method, please set add a key-value `'methodName' => true,` in property `$skipAclMethod`.

### How to apply ACL
ACL SHOULD be applied in the controller method `initialze()`.

1. Use `Phwoolcon\Admin\Auth::getUser()` to get logged in admin user;
1. Process `$skipAcl` and `$skipAclMethod`.
1. Use `Acl::isAllowed($this->user, $controller, $action)` to check if the access is allowed to the user;

```php
<?php

namespace My\App\Admin\Controllers;

use Phwoolcon\Admin\Acl;
use Phwoolcon\Admin\Auth;
use Phwoolcon\Admin\Model\Admin as AdminModel;
use Phwoolcon\Controller;
use Phwoolcon\Controller\Admin;
use Phwoolcon\Exception\HttpException;

/**
 * @acl-name Manage Blogs
 */
class BlogController extends Controller
{
    use Admin {
        Admin::initialize as _initialize;
    }

    protected $skipAcl = false;
    protected $skipAclMethod = [
        'thisIsAnOpenMethod' => true,
    ];
    /**
     * @var AdminModel
     */
    protected $user;

    public function initialize()
    {
        $this->_initialize();
        $user = Auth::getUser();
        if (!$user) {
            $this->session->set('admin_redirect_url', secureUrl($this->request->getURI()));
            throw new HttpException('Moved Temporarily', 302, ['Location' => secureUrl('/admin/auth')]);
        }
        $this->user = $user;
        $this->checkAcl();
    }

    protected function checkAcl()
    {
        if ($this->skipAcl) {
            return;
        }
        $controller = $this->router->getControllerName();
        $action = $this->router->getActionName();
        if (!empty($this->skipAclMethod[$action])) {
            return;
        }
        if (Acl::isAllowed($this->user, $controller, $action)) {
            return;
        }
        throw new HttpException('Forbidden', 403);
    }

    /**
     * @acl-name List all blogs
     */
    public function getList()
    {
        // blah blah blah
    }

    /**
     * @acl-name Access a blog
     */
    public function getEdit()
    {
        // blah blah blah
    }

    /**
     * @acl-name Create a blog
     */
    public function postCreate()
    {
        // blah blah blah
    }

    /**
     * @acl-name Update a blog
     */
    public function postEdit()
    {
        // blah blah blah
    }

    /**
     * This method will be open to all admins
     */
    public function thisIsAnOpenMethod()
    {
        // blah blah blah
    }
}
```

### How to refresh ACL resources
The ACL resources will be refreshed after the cache is cleared.

In most cases, you just need to run `bin/dump-autoload` after you updated ACL definitions.
