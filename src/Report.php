<?php

namespace Orlyapps\OrlyErrorTracking;

/**
 * What a notify callback can add to a single report – deliberately shaped like
 * Bugsnag's report object, so `$report->setMetaData([...])` callbacks keep working.
 */
class Report
{
    /** @var array<string, mixed> */
    private array $metadata = [];

    private ?string $severity = null;

    private ?string $context = null;

    /**
     * @param  array<string, mixed>  $metadata  Nested arrays are flattened to dot keys ("model.id").
     */
    public function setMetaData(array $metadata, bool $merge = true): self
    {
        $this->metadata = $merge ? array_replace_recursive($this->metadata, $metadata) : $metadata;

        return $this;
    }

    /** @param  array<string, mixed>  $metadata */
    public function addMetadata(array $metadata): self
    {
        return $this->setMetaData($metadata);
    }

    /**
     * "error", "warning" or "info" – sent as context value "severity".
     */
    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;

        return $this;
    }

    /**
     * A short label where the error happened, e.g. "Rechnungsversand" – sent as context value "location".
     */
    public function setContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return array_filter([
            ...$this->metadata,
            'severity' => $this->severity,
            'location' => $this->context,
        ], fn (mixed $value): bool => $value !== null);
    }
}
