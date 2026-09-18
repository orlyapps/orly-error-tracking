# Changelog

All notable changes to `orly-error-tracking` will be documented in this file.

## v1.0.0 - 2026-09-18

- Report everything Laravel reports to Orly, without code in the application
- Request, user and context with credentials filtered, IP shortened
- Flood protection: same error once per minute, 60 reports per minute, pause after 429
- `php artisan orly:test`
