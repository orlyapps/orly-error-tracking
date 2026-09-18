<?php

namespace Orlyapps\OrlyErrorTracking;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Log\LogManager;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Orlyapps\OrlyErrorTracking\Commands\TestCommand;
use Orlyapps\OrlyErrorTracking\Logging\CreateOrlyLogger;
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
        $this->app->singleton(RuntimeContext::class);
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
        $this->callAfterResolving('log', function (LogManager $log): void {
            $log->extend('orly', fn ($app, array $config) => (new CreateOrlyLogger)($config));
        });

        $this->rememberRunningJobsAndCommands();

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->reportable(function (Throwable $exception): void {
                    $this->app->make(Reporter::class)->report($exception);
                });
            }
        });
    }

    /**
     * Errors from workers and artisan carry the job or command, like Bugsnag's job metadata.
     */
    private function rememberRunningJobsAndCommands(): void
    {
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $this->app->make(RuntimeContext::class)->set([
                'job.name' => $event->job->resolveName(),
                'job.queue' => $event->job->getQueue(),
                'job.attempts' => $event->job->attempts(),
                'job.connection' => $event->connectionName,
            ]);
        });

        Event::listen([JobProcessed::class, JobFailed::class], fn () => $this->app->make(RuntimeContext::class)->clear());

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($this->isTrackedCommand($event->command)) {
                $this->app->make(RuntimeContext::class)->set(['command' => $event->command]);
            }
        });

        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($this->isTrackedCommand($event->command)) {
                $this->app->make(RuntimeContext::class)->clear();
            }
        });
    }

    /**
     * Worker processes run jobs; their jobs, not the worker command, are the useful context.
     */
    private function isTrackedCommand(?string $command): bool
    {
        return $command !== null && $command !== '' && ! str_starts_with($command, 'queue:') && ! str_starts_with($command, 'horizon');
    }
}
