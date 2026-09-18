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
}
