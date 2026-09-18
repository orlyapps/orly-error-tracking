<?php

namespace Orlyapps\OrlyErrorTracking;

/**
 * Remembers which queued job or artisan command is running, so an error from a
 * worker says where it came from (Bugsnag attaches the same job metadata).
 */
class RuntimeContext
{
    /** @var array<string, scalar|null> */
    private array $values = [];

    /** @param  array<string, scalar|null>  $values */
    public function set(array $values): void
    {
        $this->values = $values;
    }

    public function clear(): void
    {
        $this->values = [];
    }

    /** @return array<string, scalar|null> */
    public function all(): array
    {
        return $this->values;
    }
}
