<?php

use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Orlyapps\OrlyErrorTracking\Facades\OrlyErrorTracking;
use Orlyapps\OrlyErrorTracking\PayloadBuilder;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(['orly.test/*' => Http::response(['data' => ['accepted' => true]], 202)]);
});

function sentEvent(): array
{
    Http::assertSentCount(1);

    return Http::recorded()->first()[0]->data();
}

it('notifies a caught exception with metadata like Bugsnag::notifyException', function () {
    OrlyErrorTracking::notifyException(new RuntimeException('Lexoffice 500'), function ($report) {
        $report->setMetaData([
            'tenant' => 'hundezentrum',
            'response' => ['status' => 500, 'body' => 'Server Error'],
        ]);
        $report->setSeverity('warning');
    });

    $event = sentEvent();

    expect($event['exception_class'])->toBe(RuntimeException::class)
        ->and($event['context'])->toMatchArray([
            'tenant' => 'hundezentrum',
            'response.status' => 500,
            'response.body' => 'Server Error',
            'severity' => 'warning',
        ]);
});

it('notifies even exceptions the application does not report, like Bugsnag', function () {
    Exceptions::dontReport(InvalidArgumentException::class);

    OrlyErrorTracking::notifyException(new InvalidArgumentException('deliberately notified'));

    Http::assertSentCount(1);
});

it('notifies an error without exception like Bugsnag::notifyError, grouped by its name', function () {
    OrlyErrorTracking::notifyError('UpdateSyncedResource', 'More than one tenant found', fn ($report) => $report->setMetaData([
        'tenants' => ['a', 'b'],
    ]));

    $event = sentEvent();

    expect($event['exception_class'])->toBe('UpdateSyncedResource')
        ->and($event['message'])->toBe('More than one tenant found')
        ->and($event['context'])->toMatchArray(['tenants.0' => 'a', 'tenants.1' => 'b'])
        ->and($event['stack_trace'])->toContain('BugsnagMigrationTest.php');
});

it('filters secrets in metadata and never serialises models', function () {
    $model = new class extends Model
    {
        protected $guarded = [];
    };
    $model->fill(['iban' => 'DE83', 'name' => 'Ole']);

    OrlyErrorTracking::notifyException(new RuntimeException('x'), fn ($report) => $report->setMetaData([
        'owner' => $model,
        'customer' => ['name' => 'Ole', 'iban' => 'DE83', 'login_code' => '89424'],
    ]));

    $event = sentEvent();

    expect($event['context'])->toMatchArray(['customer.name' => 'Ole', 'customer.iban' => '[FILTERED]', 'customer.login_code' => '[FILTERED]'])
        ->and(json_encode($event))->not->toContain('DE83');
});

it('reports log records from the configured level through the orly channel', function () {
    config()->set('logging.channels.orly', ['driver' => 'orly', 'level' => 'warning']);

    Log::channel('orly')->info('below the level');
    Log::channel('orly')->warning('Stripe payment failed', ['payment_intent' => 'pi_123', 'client_secret' => 'pi_123_secret']);

    $event = sentEvent();

    expect($event['exception_class'])->toBe('log.warning')
        ->and($event['message'])->toBe('Stripe payment failed')
        ->and($event['context'])->toMatchArray([
            'log.level' => 'warning',
            'log.payment_intent' => 'pi_123',
            'log.client_secret' => '[FILTERED]',
        ]);
});

it('reports an exception passed to the log once, even if Laravel reports it too', function () {
    config()->set('logging.channels.orly', ['driver' => 'orly', 'level' => 'error']);
    $exception = new RuntimeException('Chat failed');

    Log::channel('orly')->error('Could not send chat message', ['exception' => $exception]);
    report($exception);

    $event = sentEvent();

    expect($event['exception_class'])->toBe(RuntimeException::class)
        ->and($event['context']['log.message'])->toBe('Could not send chat message');
});

it('sends scalar values from Laravel Context but no models', function () {
    Context::add('onboarding.import_batch_id', 'batch-7');
    Context::add('calendarOwner', new class extends Model
    {
        protected $attributes = ['iban' => 'DE83'];
    });

    $context = app(PayloadBuilder::class)->build(new RuntimeException('x'))['context'];

    expect($context)->toMatchArray(['onboarding.import_batch_id' => 'batch-7'])
        ->and(json_encode($context))->not->toContain('DE83');
});

it('tells which queued job failed', function () {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('App\Jobs\SendInvoice');
    $job->shouldReceive('getQueue')->andReturn('default');
    $job->shouldReceive('attempts')->andReturn(2);
    $job->shouldReceive('payload')->andReturn([]);

    event(new JobProcessing('redis', $job));
    $context = app(PayloadBuilder::class)->build(new RuntimeException('x'))['context'];
    event(new JobProcessed('redis', $job));

    expect($context)->toMatchArray(['job.name' => 'App\Jobs\SendInvoice', 'job.queue' => 'default', 'job.attempts' => 2, 'job.connection' => 'redis'])
        ->and(app(PayloadBuilder::class)->build(new RuntimeException('x'))['context'])->toBeNull();
});
