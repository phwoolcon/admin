<?php
return [
    'phwoolcon/admin' => [
        'di' => [
            10 => 'di.php',
        ],
        'class_aliases' => [
            10 => [
                'AdminAuth' => 'Phwoolcon\Admin\Auth',
                'Acl' => 'Phwoolcon\Admin\Acl',
            ],
        ],
    ],
];
