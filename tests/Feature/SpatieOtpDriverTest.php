<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Padosoft\Rebel\Bridge\SpatieOtp\Contracts\OneTimePasswordBroker;
use Padosoft\Rebel\Bridge\SpatieOtp\Drivers\SpatieOtpStepUpDriver;
use Padosoft\Rebel\Bridge\SpatieOtp\RebelSpatieOtpBridgeServiceProvider;
use Padosoft\Rebel\Bridge\SpatieOtp\Testing\FakeOneTimePasswordBroker;
use Padosoft\Rebel\Bridge\SpatieOtp\Tests\Fixtures\User;
use Padosoft\Rebel\Bridge\SpatieOtp\Tests\Fixtures\UserWithoutOtp;
use Padosoft\Rebel\Core\Assurance\Aal;
use Padosoft\Rebel\StepUp\DriverRegistry;

// ---------------------------------------------------------------------------
// Assurance
// ---------------------------------------------------------------------------

it('declares AAL2 and is not phishing-resistant with amr otp', function (): void {
    $driver = app(SpatieOtpStepUpDriver::class);
    $assurance = $driver->assurance();

    expect($assurance->aal)->toBe(Aal::Aal2)
        ->and($assurance->phishingResistant)->toBeFalse()
        ->and($assurance->amr)->toBe(['otp']);
});

it('returns key spatie_otp', function (): void {
    expect(app(SpatieOtpStepUpDriver::class)->key())->toBe('spatie_otp');
});

// ---------------------------------------------------------------------------
// isAvailableFor
// ---------------------------------------------------------------------------

it('is available for a user that uses the Spatie trait', function (): void {
    $user = User::create(['email' => 'a@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    expect($driver->isAvailableFor(otpCtx($user)))->toBeTrue();
});

it('is not available for a user without the Spatie trait', function (): void {
    // Use a fake broker that says "unsupported" for any user.
    $fake = new FakeOneTimePasswordBroker(supported: false);
    app()->instance(OneTimePasswordBroker::class, $fake);

    $user = UserWithoutOtp::create(['email' => 'b@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    expect($driver->isAvailableFor(otpCtx($user)))->toBeFalse();
});

it('returns false from isAvailableFor when broker throws', function (): void {
    $broker = new class implements OneTimePasswordBroker
    {
        public function supportsUser(Authenticatable $user): bool
        {
            throw new RuntimeException('boom');
        }

        public function send(Authenticatable $user): ?string
        {
            return null;
        }

        public function consume(Authenticatable $user, string $code): bool
        {
            return false;
        }
    };

    app()->instance(OneTimePasswordBroker::class, $broker);
    $user = User::create(['email' => 'c@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    expect($driver->isAvailableFor(otpCtx($user)))->toBeFalse();
});

// ---------------------------------------------------------------------------
// start
// ---------------------------------------------------------------------------

it('start() sends the OTP and returns the reference', function (): void {
    /** @var FakeOneTimePasswordBroker $fake */
    $fake = app(OneTimePasswordBroker::class);
    $user = User::create(['email' => 'a@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    $ref = $driver->start(otpCtx($user));

    expect($fake->sendCount)->toBe(1)
        ->and($ref)->toBe('fake-ref-1');
});

it('start() increments sendCount on each call', function (): void {
    /** @var FakeOneTimePasswordBroker $fake */
    $fake = app(OneTimePasswordBroker::class);
    $user = User::create(['email' => 'a@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    $driver->start(otpCtx($user));
    $driver->start(otpCtx($user));

    expect($fake->sendCount)->toBe(2);
});

it('start() returns null and does not throw when broker throws', function (): void {
    $broker = new class implements OneTimePasswordBroker
    {
        public function supportsUser(Authenticatable $user): bool
        {
            return true;
        }

        public function send(Authenticatable $user): ?string
        {
            throw new RuntimeException('mail server down');
        }

        public function consume(Authenticatable $user, string $code): bool
        {
            return false;
        }
    };

    app()->instance(OneTimePasswordBroker::class, $broker);
    $user = User::create(['email' => 'a@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    expect($driver->start(otpCtx($user)))->toBeNull();
});

// ---------------------------------------------------------------------------
// verify — happy path
// ---------------------------------------------------------------------------

it('verify() returns true when the code matches', function (): void {
    /** @var FakeOneTimePasswordBroker $fake */
    $fake = app(OneTimePasswordBroker::class);
    $fake->validCode = '654321';
    $fake->lastCode = '654321';

    $user = User::create(['email' => 'a@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    $driver->start(otpCtx($user));

    expect($driver->verify(otpCtx($user), '654321', 'fake-ref-1'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// verify — failure paths
// ---------------------------------------------------------------------------

it('verify() returns false for a wrong code', function (): void {
    /** @var FakeOneTimePasswordBroker $fake */
    $fake = app(OneTimePasswordBroker::class);
    $fake->validCode = '111111';
    $fake->lastCode = '111111';

    $user = User::create(['email' => 'a@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    expect($driver->verify(otpCtx($user), '999999', null))->toBeFalse();
});

it('verify() returns false for a replay (code already consumed)', function (): void {
    /** @var FakeOneTimePasswordBroker $fake */
    $fake = app(OneTimePasswordBroker::class);
    $fake->validCode = '222222';
    $fake->lastCode = '222222';

    $user = User::create(['email' => 'a@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    expect($driver->verify(otpCtx($user), '222222', null))->toBeTrue()
        // The fake single-use: second attempt must fail.
        ->and($driver->verify(otpCtx($user), '222222', null))->toBeFalse();
});

it('verify() returns false when broker throws', function (): void {
    $broker = new class implements OneTimePasswordBroker
    {
        public function supportsUser(Authenticatable $user): bool
        {
            return true;
        }

        public function send(Authenticatable $user): ?string
        {
            return null;
        }

        public function consume(Authenticatable $user, string $code): bool
        {
            throw new RuntimeException('db error');
        }
    };

    app()->instance(OneTimePasswordBroker::class, $broker);
    $user = User::create(['email' => 'a@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    expect($driver->verify(otpCtx($user), '123456', null))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Audit — code must NOT appear in audit events
// ---------------------------------------------------------------------------

it('does not log the OTP code in audit metadata', function (): void {
    /** @var FakeOneTimePasswordBroker $fake */
    $fake = app(OneTimePasswordBroker::class);
    $fake->validCode = 'SECRET';
    $fake->lastCode = 'SECRET';

    $user = User::create(['email' => 'a@b.it']);
    $driver = app(SpatieOtpStepUpDriver::class);

    $driver->start(otpCtx($user));
    $driver->verify(otpCtx($user), 'SECRET', null);

    // The audit events table should have started + verified rows with no 'SECRET' inside.
    $rows = DB::table('rebel_auth_events')->get();

    foreach ($rows as $row) {
        expect($row->event_type)->not->toContain('SECRET');
        expect((string) $row->metadata)->not->toContain('SECRET');
    }
});

// ---------------------------------------------------------------------------
// Driver registration
// ---------------------------------------------------------------------------

it('registers the spatie_otp driver in the DriverRegistry when enabled', function (): void {
    $registry = app(DriverRegistry::class);
    expect($registry->get('spatie_otp'))->not->toBeNull();
});

it('does not register the driver when config disabled', function (): void {
    // Create a fresh registry with a fresh app instance override.
    $registry = new DriverRegistry;
    app()->instance(DriverRegistry::class, $registry);

    config(['rebel-bridge-spatie-otp.drivers.spatie_otp' => false]);

    // Re-run the provider boot logic inline.
    (new RebelSpatieOtpBridgeServiceProvider(app()))->packageBooted();

    expect($registry->get('spatie_otp'))->toBeNull();
});

// ---------------------------------------------------------------------------
// FakeOneTimePasswordBroker contract
// ---------------------------------------------------------------------------

it('FakeOneTimePasswordBroker: supportsUser defaults to true', function (): void {
    $fake = new FakeOneTimePasswordBroker;
    $user = User::create(['email' => 'a@b.it']);

    expect($fake->supportsUser($user))->toBeTrue();
});

it('FakeOneTimePasswordBroker: send returns sequential references and increments sendCount', function (): void {
    $fake = new FakeOneTimePasswordBroker;
    $user = User::create(['email' => 'a@b.it']);

    $ref1 = $fake->send($user);
    $ref2 = $fake->send($user);

    expect($fake->sendCount)->toBe(2)
        ->and($ref1)->toBe('fake-ref-1')
        ->and($ref2)->toBe('fake-ref-2');
});

it('FakeOneTimePasswordBroker: consume accepts the seeded code and is single-use', function (): void {
    $fake = new FakeOneTimePasswordBroker(validCode: 'abc123');
    $user = User::create(['email' => 'a@b.it']);

    expect($fake->consume($user, 'wrong'))->toBeFalse()
        ->and($fake->consume($user, 'abc123'))->toBeTrue()
        ->and($fake->consume($user, 'abc123'))->toBeFalse(); // single-use
});
