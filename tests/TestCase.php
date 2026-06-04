<?php

declare(strict_types=1);

namespace Padosoft\Rebel\Bridge\SpatieOtp\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Padosoft\Rebel\Bridge\SpatieOtp\Contracts\OneTimePasswordBroker;
use Padosoft\Rebel\Bridge\SpatieOtp\RebelSpatieOtpBridgeServiceProvider;
use Padosoft\Rebel\Bridge\SpatieOtp\Testing\FakeOneTimePasswordBroker;
use Padosoft\Rebel\Core\RebelCoreServiceProvider;
use Padosoft\Rebel\EmailOtp\RebelEmailOtpServiceProvider;
use Padosoft\Rebel\StepUp\RebelStepUpServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            RebelCoreServiceProvider::class,
            RebelEmailOtpServiceProvider::class,
            RebelStepUpServiceProvider::class,
            RebelSpatieOtpBridgeServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('rebel-core.peppers', [1 => 'test-pepper']);
        $app['config']->set('rebel-core.pepper_current', 1);

        // Bind the fake broker so tests run fully offline (no mail, no DB internals).
        // The service provider respects an existing binding and skips its own bind.
        $app->singleton(OneTimePasswordBroker::class, fn () => new FakeOneTimePasswordBroker);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/padosoft/laravel-rebel-core/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../vendor/padosoft/laravel-rebel-email-otp/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../vendor/padosoft/laravel-rebel-step-up/database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Spatie's one-time-passwords table
        $this->loadMigrationsFrom(__DIR__.'/../vendor/spatie/laravel-one-time-passwords/database/migrations');
    }
}
