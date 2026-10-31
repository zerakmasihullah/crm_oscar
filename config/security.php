<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Require two-factor authentication for privileged roles
    |--------------------------------------------------------------------------
    |
    | When enabled, users with Owner/Admin roles must enable 2FA before
    | accessing the rest of the app (except security settings routes).
    |
    */

    'require_admin_two_factor' => env('REQUIRE_ADMIN_TWO_FACTOR', false),

];
