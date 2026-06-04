<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\SpatieOtp\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;

/**
 * Minimal user model for tests. Uses the Spatie HasOneTimePasswords trait so
 * that SpatieOtpStepUpDriver::isAvailableFor() returns true.
 */
class User extends Authenticatable
{
    use HasOneTimePasswords;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
