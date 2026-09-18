# Orly Error Tracking for Laravel

Reports your Laravel application's exceptions to [Orly](https://orly.app) – with the failing request, the user and your own context, filtered and flood-safe. No code in your application: install, set three environment variables, done.

## Installation

```bash
composer require orlyapps/orly-error-tracking
```

```dotenv
ORLY_ERROR_TRACKING_ENABLED=true
ORLY_ERROR_TRACKING_URL=https://orly.app/api/error-tracking/v1/events
ORLY_ERROR_TRACKING_KEY=your-project-ingest-key
```

The URL and the key are shown in Orly under *Projekt → Error Tracking*. Then check the connection:

```bash
php artisan orly:test
```

That's it. Everything Laravel reports – uncaught exceptions, `report()` calls, failed jobs – is sent to Orly as well. Your `dontReport` rules apply, and other reporters such as Bugsnag or Sentry keep working.

## What is sent

- exception, message and stack trace, environment, release, PHP and Laravel version
- the request: URL, method, headers, parameters, shortened IP (`79.209.99.0`), user agent
- the user: `id`, `name`, `email`
- your context (see below)

Before anything leaves the application, cookies, `Authorization` and CSRF headers, passwords, tokens, login and recovery codes, API keys and bank data are replaced by `[FILTERED]`. Parameters larger than 64 KB are left out. Orly filters again on arrival.

## Optional: context and user fields

In a service provider's `boot()` method:

```php
use Orlyapps\OrlyErrorTracking\Facades\OrlyErrorTracking;

OrlyErrorTracking::context(fn (): array => [
    'tenant' => tenant()?->getKey(),
    'plan' => tenant()?->plan,
]);

OrlyErrorTracking::user(fn ($user): array => [
    'id' => $user->getKey(),
    'name' => $user->name,
    'email' => $user->email,
    'type' => $user->type,
]);
```

Only flat values (strings, numbers, booleans, null). Pick user fields deliberately – never send the whole model.

## Flood protection

An error on every request must not become hundreds of HTTP calls per second: the same exception is reported at most once per minute, at most 60 reports per minute leave the application, and after Orly answers `429` the package pauses. Reporting uses a 0.75 s timeout without retries and never throws.

## Configuration

Optional – publish the config to change limits or add keys to filter:

```bash
php artisan vendor:publish --tag="orly-error-tracking-config"
```

```dotenv
ORLY_ERROR_TRACKING_RELEASE=          # e.g. the deployed commit
ORLY_ERROR_TRACKING_SAME_ERROR_SECONDS=60
ORLY_ERROR_TRACKING_REPORTS_PER_MINUTE=60
```

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
