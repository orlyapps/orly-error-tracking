<?php

namespace Orlyapps\OrlyErrorTracking;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Orlyapps\OrlyErrorTracking\Commands\TestCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;

class OrlyErrorTrackingServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('orly-error-tracking')
            ->hasConfigFile()
            ->hasCommand(TestCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Redactor::class, fn (): Redactor => new Redactor((array) config('orly-error-tracking.filtered_keys', [])));
        $this->app->singleton(PayloadBuilder::class);
        $this->app->singleton(Reporter::class);
        $this->app->singleton(OrlyErrorTracking::class);
    }

    /**
     * Hooks into Laravel's exception reporting: everything Laravel reports (uncaught
     * exceptions, report() calls, failed jobs) goes to Orly too, after the app's own
     * dontReport rules. Other reporters such as Bugsnag or Sentry keep running.
     */
    public function packageBooted(): void
    {
        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->reportable(function (Throwable $exception): void {
                    $this->app->make(Reporter::class)->report($exception);
                });
            }
        });
    }
}
