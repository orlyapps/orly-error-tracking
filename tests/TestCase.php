<?php

namespace Orlyapps\OrlyErrorTracking\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Orlyapps\OrlyErrorTracking\OrlyErrorTrackingServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            OrlyErrorTrackingServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('cache.default', 'array');
        config()->set('orly-error-tracking.enabled', true);
        config()->set('orly-error-tracking.url', 'https://orly.test/api/error-tracking/v1/events');
        config()->set('orly-error-tracking.key', 'project-key');
    }
}
