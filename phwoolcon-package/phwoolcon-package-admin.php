<?php
return [
    'phwoolcon/admin' => [
        'di' => [
            10 => 'di.php',
        ],
        'class_aliases' => [
            10 => [
                'Admin' => 'Phwoolcon\Admin\Auth',
                'Acl' => 'Phwoolcon\Admin\Acl',
            ],
        ],
    ],
];
