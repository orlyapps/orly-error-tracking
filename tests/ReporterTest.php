<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Orlyapps\OrlyErrorTracking\Facades\OrlyErrorTracking;
use Orlyapps\OrlyErrorTracking\PayloadBuilder;
use Orlyapps\OrlyErrorTracking\Reporter;

beforeEach(function () {
    Http::preventStrayRequests();
});

function orlyAccepts(): void
{
    Http::fake(['orly.test/*' => Http::response(['data' => ['accepted' => true]], 202)]);
}

it('reports what Laravel reports, without any code in the application', function () {
    orlyAccepts();
    report(new RuntimeException('Undefined array key 0'));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://orly.test/api/error-tracking/v1/events'
        && $request->hasHeader('Authorization', 'Bearer project-key')
        && $request['version'] === 1
        && $request['exception_class'] === RuntimeException::class
        && $request['message'] === 'Undefined array key 0');
});

it('respects the application dontReport rules', function () {
    orlyAccepts();
    Exceptions::dontReport(InvalidArgumentException::class);

    report(new InvalidArgumentException('ignored'));

    Http::assertNothingSent();
});

it('does nothing unless enabled and configured', function (string $key, mixed $value) {
    orlyAccepts();
    config()->set("orly-error-tracking.{$key}", $value);

    report(new RuntimeException('x'));

    Http::assertNothingSent();
})->with([
    'disabled' => ['enabled', false],
    'no url' => ['url', null],
    'no key' => ['key', ''],
]);

it('never breaks the application when Orly fails', function (Closure $response) {
    Http::fake(['orly.test/*' => $response]);

    app(Reporter::class)->report(new RuntimeException('x'));

    expect(true)->toBeTrue();
})->with([
    'connection error' => [fn () => fn () => throw new ConnectionException('down')],
    'server error' => [fn () => Http::response('oops', 500)],
    'unauthorized' => [fn () => Http::response(['error' => ['code' => 'invalid_credentials']], 401)],
]);

it('reports the same exception only once per minute', function () {
    orlyAccepts();
    foreach (range(1, 5) as $attempt) {
        app(Reporter::class)->report(new RuntimeException('flood'));
    }

    Http::assertSentCount(1);
});

it('keeps to the global budget per minute', function () {
    orlyAccepts();
    config()->set('orly-error-tracking.reports_per_minute', 3);

    // Different exception classes count as different errors.
    foreach ([RuntimeException::class, LogicException::class, InvalidArgumentException::class, DomainException::class, LengthException::class, OutOfRangeException::class] as $class) {
        app(Reporter::class)->report(new $class('distinct'));
    }

    Http::assertSentCount(3);
});

it('pauses after Orly answered 429', function () {
    Http::fake(['orly.test/*' => Http::response(['error' => ['code' => 'rate_limited']], 429, ['Retry-After' => '30'])]);

    app(Reporter::class)->report(new RuntimeException('first'));
    app(Reporter::class)->report(new LogicException('second'));

    Http::assertSentCount(1);
});

it('sends request, user and context, filtered', function () {
    OrlyErrorTracking::context(fn (): array => ['tenant' => 'acme', 'nested' => ['flattened']]);

    Route::post('/dogs/{dog}', fn () => response()->json(app(PayloadBuilder::class)->build(new RuntimeException('x'))))->name('dogs.update');

    $user = new User;
    $user->forceFill(['id' => 76252, 'name' => 'Syndia Cramer', 'email' => 'syndia@example.test', 'iban' => 'DE83']);

    $payload = $this->actingAs($user)
        ->withHeaders(['Authorization' => 'Bearer app-secret', 'X-Livewire' => '1'])
        ->withServerVariables(['REMOTE_ADDR' => '79.209.99.67'])
        ->postJson('/dogs/337?tab=fotos', ['name' => 'Ole', 'password' => 'geheim', 'login_code' => '89424', '_token' => 'csrf'])
        ->json();

    expect($payload['route_name'])->toBe('dogs.update')
        ->and($payload['external_user_id'])->toBe('76252')
        ->and($payload['context'])->toEqualCanonicalizing(['tenant' => 'acme', 'nested.0' => 'flattened'])
        ->and($payload['user'])->toBe(['id' => 76252, 'name' => 'Syndia Cramer', 'email' => 'syndia@example.test'])
        ->and($payload['request']['client_ip'])->toBe('79.209.99.0')
        ->and($payload['request']['headers']['authorization'])->toBe('[FILTERED]')
        ->and($payload['request']['params'])->toEqualCanonicalizing(['tab' => 'fotos', 'name' => 'Ole', 'password' => '[FILTERED]', 'login_code' => '[FILTERED]', '_token' => '[FILTERED]']);
});

it('lets the application choose further user fields, still filtered', function () {
    OrlyErrorTracking::user(fn ($user): array => ['id' => $user->id, 'type' => 'customer', 'login_code' => '89424']);

    Route::get('/me', fn () => response()->json(app(PayloadBuilder::class)->build(new RuntimeException('x'))));

    $user = new User;
    $user->forceFill(['id' => 7]);

    expect($this->actingAs($user)->getJson('/me')->json('user'))->toBe(['id' => 7, 'type' => 'customer', 'login_code' => '[FILTERED]']);
});

it('sends no request outside of HTTP, e.g. in queue workers', function () {
    $payload = app(PayloadBuilder::class)->build(new RuntimeException('x'));

    expect($payload['request'])->toBeNull()
        ->and($payload['user'])->toBeNull()
        ->and($payload['occurred_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

it('has a command to send a test exception', function () {
    orlyAccepts();
    $this->artisan('orly:test')
        ->expectsOutputToContain('accepted by Orly')
        ->assertSuccessful();

    config()->set('orly-error-tracking.enabled', false);

    $this->artisan('orly:test')->assertFailed();
});
