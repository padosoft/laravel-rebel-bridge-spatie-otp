<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\SpatieOtp\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Padosoft\Rebel\Bridge\SpatieOtp\Contracts\OneTimePasswordBroker;

/**
 * Fully in-memory {@see OneTimePasswordBroker} for offline tests. No DB, no mail,
 * no spatie internals required.
 *
 * Usage:
 *   $fake = new FakeOneTimePasswordBroker();
 *   $fake->send($user);               // records the reference; sets ->lastCode
 *   $fake->consume($user, 'wrong');   // false
 *   $fake->consume($user, $fake->lastCode); // true
 *
 * You can also pre-seed the valid code:
 *   $fake = new FakeOneTimePasswordBroker(validCode: '123456');
 */
final class FakeOneTimePasswordBroker implements OneTimePasswordBroker
{
    /** The code that will be accepted as valid by consume(). Set after send() or via constructor. */
    public string $lastCode = '';

    /** Whether supportsUser() should return true. */
    public bool $supported = true;

    /** The opaque reference returned by send(). */
    public ?string $lastReference = null;

    /** Number of times send() was called. */
    public int $sendCount = 0;

    public function __construct(
        public string $validCode = '123456',
        bool $supported = true,
    ) {
        $this->supported = $supported;
        $this->lastCode = $validCode;
    }

    public function supportsUser(Authenticatable $user): bool
    {
        return $this->supported;
    }

    public function send(Authenticatable $user): string
    {
        $this->sendCount++;
        $this->lastReference = 'fake-ref-'.$this->sendCount;

        return $this->lastReference;
    }

    public function consume(Authenticatable $user, string $code): bool
    {
        if ($this->lastCode === '') {
            return false;
        }

        $valid = hash_equals($this->lastCode, $code);

        if ($valid) {
            // Single-use: clear after consumption so a replay fails.
            $this->lastCode = '';
        }

        return $valid;
    }
}
