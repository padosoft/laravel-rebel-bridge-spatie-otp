<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\SpatieOtp\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Padosoft\Rebel\Bridge\SpatieOtp\Support\SpatieOneTimePasswordBroker;
use Padosoft\Rebel\Bridge\SpatieOtp\Testing\FakeOneTimePasswordBroker;

/**
 * Thin seam over spatie/laravel-one-time-passwords. Wrapping the Spatie API behind
 * this interface lets tests inject a fully in-memory fake (no DB, no mail) and keeps
 * the driver logic decoupled from the concrete Spatie implementation.
 *
 * The real implementation ({@see SpatieOneTimePasswordBroker})
 * calls the HasOneTimePasswords trait methods directly on the user model.
 *
 * @see FakeOneTimePasswordBroker
 */
interface OneTimePasswordBroker
{
    /**
     * Returns true when the given user's model class uses the Spatie
     * HasOneTimePasswords trait (or an equivalent interface), i.e. this driver
     * is available for the user.
     */
    public function supportsUser(Authenticatable $user): bool;

    /**
     * Creates and sends a one-time password for $user (via spatie's
     * sendOneTimePassword()). Returns an opaque reference string (the OTP record's
     * primary key cast to string) that the step-up manager may persist as the
     * challenge reference, or null if no stable reference is available.
     */
    public function send(Authenticatable $user): ?string;

    /**
     * Attempts to consume the supplied $code for $user.
     * Returns true on success (ConsumeOneTimePasswordResult::Ok), false on any
     * failure (expired, wrong, rate-limited, different origin, etc.).
     */
    public function consume(Authenticatable $user, string $code): bool;
}
