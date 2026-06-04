<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\SpatieOtp\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Padosoft\Rebel\Bridge\SpatieOtp\Contracts\OneTimePasswordBroker;
use Spatie\OneTimePasswords\Enums\ConsumeOneTimePasswordResult;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;

/**
 * Spatie-backed implementation of the {@see OneTimePasswordBroker} seam. It calls
 * the HasOneTimePasswords trait methods that spatie injects into the user model.
 *
 * The class intentionally avoids referencing the Spatie trait in type-hint
 * positions (just `Authenticatable`) so that the seam interface can be satisfied
 * by any future implementation. The actual Spatie trait usage is confirmed via
 * supportsUser() and then accessed via dynamic dispatch.
 *
 * PHPStan note: we cannot declare `Authenticatable&HasOneTimePasswords` as a type
 * because `HasOneTimePasswords` is a PHP trait, not an interface — PHPStan would
 * report it as an unresolvable type. We therefore use `method_exists` guards and
 * dynamic calls, which PHPStan accepts without suppression.
 */
final class SpatieOneTimePasswordBroker implements OneTimePasswordBroker
{
    public function supportsUser(Authenticatable $user): bool
    {
        return in_array(HasOneTimePasswords::class, array_values(self::traitUses($user)), true);
    }

    public function send(Authenticatable $user): ?string
    {
        if (method_exists($user, 'sendOneTimePassword')) {
            $user->sendOneTimePassword();
        }

        // spatie's sendOneTimePassword() keeps its own state; no opaque reference
        // that we can reliably surface here.
        return null;
    }

    public function consume(Authenticatable $user, string $code): bool
    {
        if (! method_exists($user, 'consumeOneTimePassword')) {
            return false;
        }

        $result = $user->consumeOneTimePassword($code);

        if (! $result instanceof ConsumeOneTimePasswordResult) {
            return false;
        }

        return $result === ConsumeOneTimePasswordResult::Ok;
    }

    /**
     * Returns all traits used by an object's class and all its parent classes.
     *
     * class_uses() returns array<class-string, class-string> (trait name => trait name).
     *
     * @return array<class-string, class-string>
     */
    private static function traitUses(object $object): array
    {
        /** @var array<class-string, class-string> $traits */
        $traits = [];

        $class = $object::class;

        do {
            /** @var array<class-string, class-string> $classTraits */
            $classTraits = class_uses($class) ?: [];
            $traits = array_merge($traits, $classTraits);
        } while ($class = get_parent_class($class));

        return $traits;
    }
}
