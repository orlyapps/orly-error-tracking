# Changelog

All notable changes to `orly-error-tracking` will be documented in this file.

## v1.1.0 - 2026-09-18

- `OrlyErrorTracking::notifyException()` and `notifyError()` with Bugsnag-style `$report->setMetaData()` callbacks
- `orly` log channel driver: log records from a configurable level become errors, exceptions in the log context are reported once
- Laravel Context values and the running job or artisan command are added automatically
- Nested metadata is flattened to dot keys; models are never serialised

## v1.0.0 - 2026-09-18

- Report everything Laravel reports to Orly, without code in the application
- Request, user and context with credentials filtered, IP shortened
- Flood protection: same error once per minute, 60 reports per minute, pause after 429
- `php artisan orly:test`
