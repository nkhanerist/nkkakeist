<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->bootstrapTestingEnvironmentVariables();

        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function bootstrapTestingEnvironmentVariables(): void
    {
        $appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: null;

        if ($appEnv !== 'testing') {
            return;
        }

        $keys = [
            'APP_ENV',
            'DB_CONNECTION',
            'DB_DATABASE',
            'DB_URL',
            'CACHE_STORE',
            'SESSION_DRIVER',
            'QUEUE_CONNECTION',
            'MAIL_MAILER',
            'PULSE_ENABLED',
            'TELESCOPE_ENABLED',
            'NIGHTWATCH_ENABLED',
        ];

        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

            if ($value === null) {
                continue;
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
