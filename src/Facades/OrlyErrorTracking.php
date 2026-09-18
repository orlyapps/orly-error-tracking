<?php

namespace Orlyapps\OrlyErrorTracking\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Orlyapps\OrlyErrorTracking\OrlyErrorTracking context(\Closure $resolver)
 * @method static \Orlyapps\OrlyErrorTracking\OrlyErrorTracking user(\Closure $resolver)
 * @method static void report(\Throwable $exception)
 * @method static void notifyException(\Throwable $exception, callable|null $callback = null)
 * @method static void notifyError(string $name, string $message, callable|null $callback = null)
 *
 * @see \Orlyapps\OrlyErrorTracking\OrlyErrorTracking
 */
class OrlyErrorTracking extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Orlyapps\OrlyErrorTracking\OrlyErrorTracking::class;
    }
}
