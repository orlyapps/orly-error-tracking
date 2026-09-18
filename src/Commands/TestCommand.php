<?php

namespace Orlyapps\OrlyErrorTracking\Commands;

use Illuminate\Console\Command;
use Orlyapps\OrlyErrorTracking\Reporter;
use RuntimeException;
use Throwable;

class TestCommand extends Command
{
    public $signature = 'orly:test';

    public $description = 'Send a test exception to Orly and show whether it arrived';

    public function handle(Reporter $reporter): int
    {
        if (! $reporter->isConfigured()) {
            $this->components->error('Orly error tracking is not configured. Set ORLY_ERROR_TRACKING_ENABLED=true, ORLY_ERROR_TRACKING_URL and ORLY_ERROR_TRACKING_KEY (then php artisan config:clear).');

            return self::FAILURE;
        }

        try {
            $response = $reporter->send(new RuntimeException('Orly test exception from '.config('app.name').' ('.app()->environment().')'));
        } catch (Throwable $exception) {
            $this->components->error('Orly could not be reached: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($response->successful()) {
            $this->components->info('Test exception accepted by Orly (HTTP '.$response->status().'). It appears under "Fehler" in your project within seconds.');

            return self::SUCCESS;
        }

        $this->components->error('Orly answered HTTP '.$response->status().': '.$response->json('error.message', $response->body()));

        return self::FAILURE;
    }
}
