<?php

namespace Orlyapps\OrlyErrorTracking;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the event Orly's ingest endpoint expects (protocol version 1).
 */
class PayloadBuilder
{
    /** @var (Closure(): array<string, scalar|null>)|null */
    private ?Closure $context = null;

    /** @var (Closure(Authenticatable): array<string, scalar|null>)|null */
    private ?Closure $user = null;

    public function __construct(private Redactor $redactor) {}

    /** @param  Closure(): array<string, scalar|null>  $resolver */
    public function resolveContextUsing(Closure $resolver): void
    {
        $this->context = $resolver;
    }

    /** @param  Closure(Authenticatable): array<string, scalar|null>  $resolver */
    public function resolveUserUsing(Closure $resolver): void
    {
        $this->user = $resolver;
    }

    /** @return array<string, mixed> */
    public function build(Throwable $exception): array
    {
        // Only a routed request is a real HTTP request; artisan and queue workers have none.
        $request = app()->bound('request') && app('request')->route() !== null ? app('request') : null;
        $user = $request?->user();

        return [
            'version' => 1,
            'event_uuid' => (string) Str::uuid(),
            'exception_class' => Str::substr($exception::class, 0, 1024),
            'message' => Str::substr($exception->getMessage(), 0, 16384),
            'stack_trace' => Str::substr($exception->getTraceAsString(), 0, 204800),
            'occurred_at' => now()->toIso8601String(),
            'environment' => Str::substr(app()->environment(), 0, 64),
            'release' => $this->limited(config('orly-error-tracking.release')),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'request_method' => $request?->getMethod(),
            'request_path' => $this->limited($request?->getPathInfo()),
            'route_name' => $this->limited($request?->route()?->getName()),
            'external_user_id' => $this->limited($user?->getAuthIdentifier()),
            'context' => $this->flat(fn (): mixed => $this->context !== null ? ($this->context)() : []),
            'user' => $user === null ? null : $this->flat(fn (): mixed => $this->user !== null
                ? ($this->user)($user)
                : ['id' => $user->getAuthIdentifier(), 'name' => $user->name ?? null, 'email' => $user->email ?? null]),
            'request' => $request === null ? null : $this->request($request),
        ];
    }

    /** @return array<string, mixed> */
    private function request(Request $request): array
    {
        $params = $this->redactor->values($request->except(array_keys($request->allFiles())));

        if (strlen((string) json_encode($params)) > (int) config('orly-error-tracking.maximum_parameter_bytes', 65536)) {
            $params = ['_truncated' => 'Parameters larger than the configured limit'];
        }

        return array_filter([
            'url' => Str::substr($request->url(), 0, 2048),
            'method' => $request->getMethod(),
            'headers' => $this->redactor->headers($request->headers->all()),
            'params' => $params,
            'client_ip' => $this->shortenedIp($request->ip()),
            'user_agent' => $this->limited($request->userAgent()),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * 79.209.99.67 → 79.209.99.0 (IPv6: /48): enough for region and provider, no longer a person.
     */
    private function shortenedIp(?string $ip): ?string
    {
        $packed = $ip !== null ? @inet_pton($ip) : false;

        if ($packed === false) {
            return null;
        }

        $keep = strlen($packed) === 4 ? 3 : 6;

        return inet_ntop(substr($packed, 0, $keep).str_repeat("\0", strlen($packed) - $keep)) ?: null;
    }

    /**
     * Flat scalar values only (max. 50 keys, 1024 characters), sensitive keys filtered.
     * A failing resolver never prevents the report – it just goes out without this part.
     *
     * @param  Closure(): mixed  $resolver
     * @return array<string, scalar|null>|null
     */
    private function flat(Closure $resolver): ?array
    {
        try {
            $values = $resolver();
        } catch (Throwable) {
            return null;
        }

        $flat = [];

        foreach (is_array($values) ? $values : [] as $key => $value) {
            if (! is_string($key) || ($value !== null && ! is_scalar($value)) || count($flat) >= 50) {
                continue;
            }

            $flat[Str::substr($key, 0, 100)] = $this->redactor->isSensitive($key)
                ? Redactor::FILTERED
                : (is_string($value) ? Str::substr($value, 0, 1024) : $value);
        }

        return $flat === [] ? null : $flat;
    }

    private function limited(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Str::substr((string) $value, 0, 1024);
    }
}
