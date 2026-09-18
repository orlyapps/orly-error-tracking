<?php

namespace Orlyapps\OrlyErrorTracking;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

class OrlyErrorTracking
{
    public function __construct(
        private PayloadBuilder $payloadBuilder,
        private Reporter $reporter,
    ) {}

    /**
     * Extra metadata for every report, e.g. the tenant:
     * OrlyErrorTracking::context(fn (): array => ['tenant' => tenant()?->id]);
     *
     * @param  Closure(): array<string, scalar|null>  $resolver
     */
    public function context(Closure $resolver): self
    {
        $this->payloadBuilder->resolveContextUsing($resolver);

        return $this;
    }

    /**
     * Which user fields are sent. Default: id, name, email. Pick fields deliberately:
     * OrlyErrorTracking::user(fn ($user): array => ['id' => $user->id, 'name' => $user->name, 'type' => $user->type]);
     *
     * @param  Closure(Authenticatable): array<string, scalar|null>  $resolver
     */
    public function user(Closure $resolver): self
    {
        $this->payloadBuilder->resolveUserUsing($resolver);

        return $this;
    }

    public function report(Throwable $exception): void
    {
        $this->reporter->report($exception);
    }

    /**
     * Reports a caught exception – drop-in for Bugsnag::notifyException():
     * OrlyErrorTracking::notifyException($e, fn ($report) => $report->setMetaData(['tenant' => tenant('id')]));
     *
     * Unlike report() it ignores the application's dontReport rules, like Bugsnag does.
     *
     * @param  (callable(Report): mixed)|null  $callback
     */
    public function notifyException(Throwable $exception, ?callable $callback = null): void
    {
        $this->reporter->report($exception, $this->metadataFrom($callback));
    }

    /**
     * Reports a problem without an exception – drop-in for Bugsnag::notifyError('Name', 'Message', $callback).
     * The name becomes the error class in Orly, so equal names are grouped.
     *
     * @param  (callable(Report): mixed)|null  $callback
     */
    public function notifyError(string $name, string $message, ?callable $callback = null): void
    {
        $this->reporter->report(new NotifiedError($message), $this->metadataFrom($callback), $name);
    }

    /**
     * @param  (callable(Report): mixed)|null  $callback
     * @return array<string, mixed>
     */
    private function metadataFrom(?callable $callback): array
    {
        if ($callback === null) {
            return [];
        }

        try {
            $report = new Report;
            $callback($report);

            return $report->metadata();
        } catch (Throwable) {
            // A failing callback must not prevent the report.
            return [];
        }
    }
}
