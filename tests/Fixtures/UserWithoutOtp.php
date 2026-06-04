<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\SpatieOtp\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * User model WITHOUT the HasOneTimePasswords trait, used to assert that
 * SpatieOtpStepUpDriver::isAvailableFor() returns false for unsupported users.
 */
class UserWithoutOtp extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
