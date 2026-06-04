<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\SpatieOtp;

use Illuminate\Contracts\Config\Repository;
use Padosoft\Rebel\Bridge\SpatieOtp\Contracts\OneTimePasswordBroker;
use Padosoft\Rebel\Bridge\SpatieOtp\Drivers\SpatieOtpStepUpDriver;
use Padosoft\Rebel\Bridge\SpatieOtp\Support\SpatieOneTimePasswordBroker;
use Padosoft\Rebel\StepUp\DriverRegistry;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;

/**
 * Bridges spatie/laravel-one-time-passwords into the Laravel Rebel step-up suite.
 *
 * The driver is registered in DriverRegistry only when:
 *   1. The config key `rebel-bridge-spatie-otp.drivers.spatie_otp` is true (default); AND
 *   2. The Spatie HasOneTimePasswords trait is present in the project
 *      (class_exists check — no hard dependency at runtime).
 *
 * This means the package installs safely on projects that do NOT have Spatie's OTP
 * library, and the driver simply stays unregistered with no errors.
 */
final class RebelSpatieOtpBridgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-rebel-bridge-spatie-otp')
            ->hasConfigFile('rebel-bridge-spatie-otp');
    }

    public function packageBooted(): void
    {
        $this->bindBroker();
        $this->registerStepUpDriver();
    }

    /**
     * Bind the OneTimePasswordBroker contract.
     * When Spatie is installed, use the real spatie-backed implementation.
     * When absent (e.g. test environment with fake already bound), leave as-is.
     */
    private function bindBroker(): void
    {
        if (! $this->app->bound(OneTimePasswordBroker::class)) {
            if ($this->spatieInstalled()) {
                $this->app->singleton(
                    OneTimePasswordBroker::class,
                    SpatieOneTimePasswordBroker::class,
                );
            }
        }
    }

    private function registerStepUpDriver(): void
    {
        $config = $this->app->make(Repository::class);

        if ($config->get('rebel-bridge-spatie-otp.drivers.spatie_otp', true) !== true) {
            return;
        }

        if (! $this->spatieInstalled()) {
            return;
        }

        if (! $this->app->bound(OneTimePasswordBroker::class)) {
            return;
        }

        $registry = $this->app->make(DriverRegistry::class);
        $registry->register($this->app->make(SpatieOtpStepUpDriver::class));
    }

    /**
     * Feature-detect spatie/laravel-one-time-passwords by checking for its trait.
     * We check the trait (not a facade or service provider) because the trait is
     * the public contract users add to their model.
     *
     * NOTE: HasOneTimePasswords is a PHP trait, so we use trait_exists(), not class_exists().
     */
    private function spatieInstalled(): bool
    {
        return trait_exists(HasOneTimePasswords::class);
    }
}
