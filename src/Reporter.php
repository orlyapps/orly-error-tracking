<?php

namespace Orlyapps\OrlyErrorTracking;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Sends exceptions to Orly without ever affecting the application: short timeouts,
 * no retries, nothing thrown, logged or reported from here.
 */
class Reporter
{
    /**
     * Exceptions already sent in this process – Laravel's handler and the log channel
     * may both see the same exception, it must reach Orly only once.
     *
     * @var \WeakMap<Throwable, true>
     */
    private \WeakMap $reported;

    public function __construct(private PayloadBuilder $payloadBuilder)
    {
        $this->reported = new \WeakMap;
    }

    public function isConfigured(): bool
    {
        return (bool) config('orly-error-tracking.enabled')
            && filled(config('orly-error-tracking.url'))
            && filled(config('orly-error-tracking.key'));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function report(Throwable $exception, array $metadata = [], ?string $exceptionClass = null): void
    {
        if (! $this->isConfigured() || isset($this->reported[$exception]) || $this->isThrottled($exception, $exceptionClass)) {
            return;
        }

        $this->reported[$exception] = true;

        try {
            $this->send($exception, $metadata, $exceptionClass);
        } catch (Throwable) {
            // Reporting must never break the application or report itself.
        }
    }

    /**
     * Sends right away, without flood protection – for `php artisan orly:test`.
     */
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function send(Throwable $exception, array $metadata = [], ?string $exceptionClass = null): Response
    {
        $response = Http::asJson()
            ->withToken((string) config('orly-error-tracking.key'))
            ->connectTimeout((float) config('orly-error-tracking.connect_timeout', 0.25))
            ->timeout((float) config('orly-error-tracking.timeout', 0.75))
            ->post((string) config('orly-error-tracking.url'), $this->payloadBuilder->build($exception, $metadata, $exceptionClass));

        if ($response->status() === 429) {
            Cache::put('orly-error-tracking:paused', true, max(1, (int) ($response->header('Retry-After') ?: 60)));
        }

        return $response;
    }

    /**
     * An error flood (e.g. the same exception on every request) must not turn into
     * hundreds of HTTP calls per second: repeat errors, a global budget and a pause
     * after Orly answered 429 keep it to a handful per minute.
     */
    private function isThrottled(Throwable $exception, ?string $exceptionClass): bool
    {
        try {
            if (Cache::has('orly-error-tracking:paused')) {
                return true;
            }

            $sameError = 'orly-error-tracking:'.sha1(($exceptionClass ?? $exception::class).'|'.$exception->getFile().'|'.$exception->getLine());

            if (! Cache::add($sameError, true, (int) config('orly-error-tracking.same_error_seconds', 60))) {
                return true;
            }

            if (RateLimiter::tooManyAttempts('orly-error-tracking', (int) config('orly-error-tracking.reports_per_minute', 60))) {
                return true;
            }

            RateLimiter::hit('orly-error-tracking', 60);

            return false;
        } catch (Throwable) {
            // Without a working cache (e.g. the database is down) the error is still worth reporting.
            return false;
        }
    }
}
