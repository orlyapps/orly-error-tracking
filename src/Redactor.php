<?php

namespace Orlyapps\OrlyErrorTracking;

use Illuminate\Support\Str;

/**
 * Replaces credentials, tokens and bank data by [FILTERED] before anything leaves
 * the application. Orly filters again on arrival.
 */
class Redactor
{
    public const FILTERED = '[FILTERED]';

    /** @var list<string> */
    public const FILTERED_HEADERS = ['cookie', 'authorization', 'proxy-authorization', 'x-csrf-token', 'x-xsrf-token', 'x-api-key'];

    /** @var list<string> */
    private const SENSITIVE_WORDS = ['password', 'passwort', 'passwd', 'pwd', 'secret', 'token', 'otp', 'pin', 'iban', 'bic', 'cvc', 'cvv', 'session', 'cookie', 'signature', 'salt', 'credential', 'credentials', 'authorization'];

    /**
     * Combinations that are secrets although their words alone are not ("login_code", "api_key").
     */
    private const SENSITIVE_PATTERN = '/(^|_)(login|auth|verification|recovery|reset|factor|security|access)_codes?($|_)|(^|_)(api|private|secret|access|license)_key($|_)|(^|_)card_number($|_)/';

    /**
     * @param  list<string>  $additionalKeys
     */
    public function __construct(private array $additionalKeys = []) {}

    public function isSensitive(string $key): bool
    {
        $normalized = (string) preg_replace('/[^a-z0-9]+/', '_', Str::lower(Str::snake($key)));

        return array_intersect(explode('_', $normalized), self::SENSITIVE_WORDS) !== []
            || preg_match(self::SENSITIVE_PATTERN, $normalized) === 1
            || in_array(Str::lower($key), array_map(Str::lower(...), $this->additionalKeys), true);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function values(array $data, int $depth = 0): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $data[$key] = self::FILTERED;
            } elseif (is_array($value) && $depth < 12) {
                $data[$key] = $this->values($value, $depth + 1);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, list<string|null>>  $headers
     * @return array<string, string>
     */
    public function headers(array $headers): array
    {
        $flat = [];

        foreach (array_slice($headers, 0, 100, true) as $name => $values) {
            $flat[(string) $name] = in_array(Str::lower((string) $name), self::FILTERED_HEADERS, true)
                ? self::FILTERED
                : Str::substr(implode(', ', array_filter($values, 'is_string')), 0, 4096);
        }

        return $flat;
    }
}
