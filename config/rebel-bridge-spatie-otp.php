<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Step-up Drivers
    |--------------------------------------------------------------------------
    |
    | Each key controls whether the corresponding step-up driver is registered
    | into the Laravel Rebel DriverRegistry on boot.
    |
    | Setting a driver to `false` prevents it from being registered even when
    | spatie/laravel-one-time-passwords is installed. Useful when you want to
    | install the package without activating the driver yet, or to disable it
    | temporarily without uninstalling.
    |
    */

    'drivers' => [

        /*
        | Enable the spatie_otp step-up driver.
        |
        | Requirements (all must be true for the driver to register):
        |   - This value must be `true`.
        |   - spatie/laravel-one-time-passwords must be installed.
        |   - The user model must use the HasOneTimePasswords trait.
        |
        | The driver provides AAL2, non-phishing-resistant assurance with amr=['otp'].
        */
        'spatie_otp' => (bool) env('REBEL_SPATIE_OTP_DRIVER_ENABLED', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Channel
    |--------------------------------------------------------------------------
    |
    | The channel label recorded in audit events (rebel_auth_events.channel).
    | Defaults to 'otp'. You might change this to 'email' or 'sms' to match how
    | Spatie delivers the one-time password in your project.
    |
    */
    'audit_channel' => env('REBEL_SPATIE_OTP_AUDIT_CHANNEL', 'otp'),

];
