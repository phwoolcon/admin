<?php

return [
    'auth' => [
        'adapter' => 'Phwoolcon\Admin\Auth\Adapter\Generic',
        'options' => [
            'user_model' => 'Phwoolcon\Admin\Model\Admin',
            'user_fields' => [
                'login_fields' => ['username', 'email'],
                'password_field' => 'password',
            ],
            'session_key' => 'admin',
            'remember_login' => [
                'cookie_key' => 'remember-adm',
                'ttl' => 604800,
            ],
            'uid_key' => 'id',
            'security' => [
                'default_hash' => Phalcon\Security::CRYPT_BLOWFISH_Y,
                'work_factor' => 10,
            ],
            'hints' => [
                'invalid_password' => 'Invalid password',
                'invalid_user_credential' => 'Invalid user credential',
                'user_credential_registered' => 'User credential registered',
                'unable_to_save_user' => 'Unable to save user',
            ],
        ],
    ],
    'acl' => [
        'cache_key' => 'admin_acl_adapter',
        'superuser_role' => 'admin',
    ],
];
