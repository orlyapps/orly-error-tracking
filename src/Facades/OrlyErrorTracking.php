<?php

namespace Orlyapps\OrlyErrorTracking\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Orlyapps\OrlyErrorTracking\OrlyErrorTracking context(\Closure $resolver)
 * @method static \Orlyapps\OrlyErrorTracking\OrlyErrorTracking user(\Closure $resolver)
 * @method static void report(\Throwable $exception)
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
