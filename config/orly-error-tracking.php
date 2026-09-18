<?php

return [

    /*
     * Reporting is off unless enabled and both URL and key are set.
     */
    'enabled' => (bool) env('ORLY_ERROR_TRACKING_ENABLED', false),

    /*
     * The ingest endpoint and the project key from Orly (project settings → Error Tracking).
     */
    'url' => env('ORLY_ERROR_TRACKING_URL'),

    'key' => env('ORLY_ERROR_TRACKING_KEY'),

    /*
     * Optional release identifier, e.g. the deployed commit.
     */
    'release' => env('ORLY_ERROR_TRACKING_RELEASE'),

    /*
     * Flood protection: the same exception is reported at most once per this many
     * seconds, and at most this many reports per minute leave the application.
     */
    'same_error_seconds' => (int) env('ORLY_ERROR_TRACKING_SAME_ERROR_SECONDS', 60),

    'reports_per_minute' => (int) env('ORLY_ERROR_TRACKING_REPORTS_PER_MINUTE', 60),

    /*
     * Reporting must never slow the application down noticeably.
     */
    'connect_timeout' => 0.25,

    'timeout' => 0.75,

    /*
     * Additional parameter and user keys to replace by [FILTERED], on top of the
     * built-in list (passwords, tokens, secrets, login codes, bank data, …).
     */
    'filtered_keys' => [],

    /*
     * Send scalar values from Laravel's Context (Context::add) with every report.
     * Objects such as Eloquent models are never serialised.
     */
    'laravel_context' => true,

    /*
     * Parameters above this size are left out of the report.
     */
    'maximum_parameter_bytes' => 65536,
];
