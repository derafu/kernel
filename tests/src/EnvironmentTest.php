<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsKernel;

use Derafu\Kernel\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Environment::class)]
class EnvironmentTest extends TestCase
{
    private string $originalEnv;

    protected function setUp(): void
    {
        // Backup original environment.
        $this->originalEnv = $_ENV['DATABASE_HOST'] ?? '';

        // Clean up environment for tests.
        unset($_ENV['DATABASE_HOST']);
        unset($_ENV['DATABASE_USER']);
        unset($_ENV['API_KEY']);
        unset($_ENV['TYPED_BOOL_VAR']);
        unset($_ENV['TYPED_INT_VAR']);
        unset($_ENV['TYPED_FLOAT_VAR']);
        unset($_ENV['TYPED_JSON_VAR']);
    }

    protected function tearDown(): void
    {
        // Restore original environment.
        if ($this->originalEnv !== '') {
            $_ENV['DATABASE_HOST'] = $this->originalEnv;
        }

        // Clean up getenv fallback test variable.
        putenv('GETENV_ONLY_VAR');
    }

    public function testEnvironmentVariableLoading(): void
    {
        // Set up test environment variables.
        $_ENV['DATABASE_HOST'] = 'test_host';
        $_ENV['DATABASE_USER'] = 'test_user';
        $_ENV['API_KEY'] = 'test_key';

        $environment = new TestEnvironmentForEnv('test', true);

        $this->assertSame('test_host', $environment->getEnv('DATABASE_HOST'));
        $this->assertSame('test_user', $environment->getEnv('DATABASE_USER'));
        $this->assertSame('test_key', $environment->getEnv('API_KEY'));
    }

    public function testEnvironmentVariableWithDefault(): void
    {
        $environment = new TestEnvironmentForEnv('test', true);

        $this->assertSame('default_value', $environment->getEnv('NON_EXISTENT_VAR', 'default_value'));
        $this->assertNull($environment->getEnv('ANOTHER_NON_EXISTENT_VAR'));
    }

    public function testGetAllEnvironmentVariables(): void
    {
        // Set up test environment variables.
        $_ENV['DATABASE_HOST'] = 'test_host';
        $_ENV['DATABASE_USER'] = 'test_user';

        $environment = new TestEnvironmentForEnv('test', true);
        $envVars = $environment->getEnvVars();

        $this->assertArrayHasKey('DATABASE_HOST', $envVars);
        $this->assertArrayHasKey('DATABASE_USER', $envVars);
        $this->assertSame('test_host', $envVars['DATABASE_HOST']);
        $this->assertSame('test_user', $envVars['DATABASE_USER']);
    }

    public function testEnvironmentVariablesInToArray(): void
    {
        $_ENV['DATABASE_HOST'] = 'test_host';

        $environment = new TestEnvironmentForEnv('test', true);
        $array = $environment->toArray();

        $this->assertArrayHasKey('env_vars', $array);
        $this->assertArrayHasKey('DATABASE_HOST', $array['env_vars']);
        $this->assertSame('test_host', $array['env_vars']['DATABASE_HOST']);
    }

    public function testEnvironmentVariablePrecedence(): void
    {
        // Set variables in different sources.
        $_ENV['TEST_VAR'] = 'env_value';
        $_SERVER['TEST_VAR'] = 'server_value';

        $environment = new TestEnvironmentForEnv('test', true);

        // Should prefer $_ENV over $_SERVER.
        $this->assertSame('env_value', $environment->getEnv('TEST_VAR'));
    }

    public function testServerVariableFallback(): void
    {
        // Only set in $_SERVER.
        $_SERVER['SERVER_VAR'] = 'server_value';
        unset($_ENV['SERVER_VAR']);

        $environment = new TestEnvironmentForEnv('test', true);

        $this->assertSame('server_value', $environment->getEnv('SERVER_VAR'));
    }

    public function testGetEnvWithTypeCasting(): void
    {
        $_ENV['TYPED_BOOL_VAR'] = 'true';
        $_ENV['TYPED_INT_VAR'] = '42';
        $_ENV['TYPED_FLOAT_VAR'] = '3.14';
        $_ENV['TYPED_JSON_VAR'] = '{"key":"value","count":5}';

        $environment = new TestEnvironmentForEnv('test', true);

        $this->assertTrue($environment->getEnv('bool:TYPED_BOOL_VAR'));
        $this->assertSame(42, $environment->getEnv('int:TYPED_INT_VAR'));
        $this->assertSame(3.14, $environment->getEnv('float:TYPED_FLOAT_VAR'));
        $this->assertSame(['key' => 'value', 'count' => 5], $environment->getEnv('json:TYPED_JSON_VAR'));

        // Also verify that false-y string values cast to false for bool.
        $_ENV['TYPED_BOOL_VAR'] = 'false';
        $environment2 = new TestEnvironmentForEnv('test', true);
        $this->assertFalse($environment2->getEnv('bool:TYPED_BOOL_VAR'));
    }

    public function testGetEnvFallsBackToGetenv(): void
    {
        // Variable is not in $_ENV or $_SERVER.
        unset($_ENV['GETENV_ONLY_VAR']);
        unset($_SERVER['GETENV_ONLY_VAR']);

        // Set it only via putenv().
        putenv('GETENV_ONLY_VAR=from_getenv');

        $environment = new TestEnvironmentForEnv('test', true);

        $this->assertSame('from_getenv', $environment->getEnv('GETENV_ONLY_VAR'));
    }
}

class TestEnvironmentForEnv extends Environment
{
    public function getProjectDir(): string
    {
        return dirname(__DIR__) . '/fixtures';
    }
}
