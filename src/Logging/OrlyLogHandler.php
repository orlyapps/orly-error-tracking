<?php

namespace Orlyapps\OrlyErrorTracking\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Orlyapps\OrlyErrorTracking\NotifiedError;
use Orlyapps\OrlyErrorTracking\Reporter;
use Throwable;

/**
 * The "orly" log channel: log records from the configured level upwards become
 * errors in Orly, like Bugsnag's log channel. A record carrying an exception
 * (['exception' => $e]) reports that exception; others are grouped by level and
 * message, e.g. "log.warning" – "Stripe payment failed".
 */
class OrlyLogHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        try {
            $context = $record->context;
            $exception = $context['exception'] ?? null;
            unset($context['exception']);

            $metadata = ['log.level' => $record->level->toPsrLogLevel(), 'log.channel' => $record->channel, 'log' => $context];

            if ($exception instanceof Throwable) {
                app(Reporter::class)->report($exception, [...$metadata, 'log.message' => $record->message]);

                return;
            }

            app(Reporter::class)->report(
                new NotifiedError($record->message),
                $metadata,
                'log.'.$record->level->toPsrLogLevel(),
            );
        } catch (Throwable) {
            // Logging must never fail because of error tracking.
        }
    }
}
