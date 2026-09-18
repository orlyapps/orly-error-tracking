<?php

namespace Orlyapps\OrlyErrorTracking\Logging;

use Monolog\Level;
use Monolog\Logger;

/**
 * config/logging.php: 'orly' => ['driver' => 'orly', 'level' => 'warning'],
 */
class CreateOrlyLogger
{
    /** @param  array<string, mixed>  $config */
    public function __invoke(array $config): Logger
    {
        return new Logger('orly', [
            new OrlyLogHandler(Level::fromName((string) ($config['level'] ?? 'error'))),
        ]);
    }
}
