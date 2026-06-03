<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\SpatieOtp;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Service provider for the laravel-rebel-bridge-spatie-otp package (initial skeleton).
 * The full implementation will arrive in its roadmap macro-task.
 */
final class RebelSpatieOtpBridgeServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laravel-rebel-bridge-spatie-otp');
    }
}
