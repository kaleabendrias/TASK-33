<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Field-Level Encryption
    |--------------------------------------------------------------------------
    | AES-256-CBC key for encrypting sensitive PII fields (phone numbers, etc).
    | Uses Laravel's APP_KEY by default — override for independent rotation.
    */
    'field_key' => env('FIELD_ENCRYPTION_KEY', env('APP_KEY')),
];
