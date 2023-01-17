<?php

return [
    'mailers' => [
        'O365' => [
            'tenant' => env('OFFICE365MAIL_TENANT', null),
            'client_id' => env('OFFICE365MAIL_CLIENT_ID', null),
            'client_secret' => env('OFFICE365MAIL_CLIENT_SECRET', null)
        ]
    ]
];
