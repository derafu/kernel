<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Kernel;

use Composer\InstalledVersions;
use Derafu\Kernel\Contract\EnvironmentInterface;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Implementation of the environment configuration and structure.
 *
 * This class manages:
 *
 *   - Environment and debug settings.
 *   - Project directory structure.
 *   - Configuration loading from PHP and YAML files.
 */
class Environment implements EnvironmentInterface
{
    /**
     * Environment variables loaded from .env files.
     *
     * @var array<string, string>
     */
    protected array $envVars = [];

    /**
     * Creates a new Environment instance.
     *
     * @param string $name The environment name (e.g., 'dev', 'prod').
     * @param bool $debug Whether to enable debug mode.
     * @param array<string, mixed> $context
     * @param array{project?: string|null, cache?: string|null, config?: string|null, log?: string|null, resources?: string|null} $directories
     * The directories of the environment with keys:
     *   - project: The project's root directory.
     *   - cache: The cache directory.
     *   - config: The configuration directory.
     *   - log: The logs directory.
     *   - resources: The resources directory.
     */
    public function __construct(
        protected readonly string $name,
        protected readonly bool $debug = false,
        protected readonly array $context = [],
        protected array $directories = [
            'project' => null,
            'cache' => null,
            'config' => null,
            'log' => null,
            'resources' => null,
        ]
    ) {
        // Load environment variables.
        $this->loadEnvironmentVariables();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * {@inheritDoc}
     */
    public function getEnvVars(): array
    {
        return $this->envVars;
    }

    /**
     * {@inheritDoc}
     */
    public function getEnv(string $name, mixed $default = null): mixed
    {
        $type = null;

        if (str_contains($name, ':')) {
            [$type, $name] = explode(':', $name, 2);
        }

        $value = $this->envVars[$name]
            ?? $_ENV[$name]
            ?? $_SERVER[$name]
            ?? (($env = getenv($name)) !== false ? $env : null)
            ?? $default
        ;

        if ($type === null) {
            return $value;
        }

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode((string)$value, true),
            default => $value,
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getProjectDir(): string
    {
        if (!isset($this->directories['project'])) {
            $routingPackagePath = realpath(
                InstalledVersions::getInstallPath('symfony/dependency-injection')
            );
            $this->directories['project'] = dirname($routingPackagePath, 3);
        }

        return $this->directories['project'];
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheDir(): string
    {
        if (!isset($this->directories['cache'])) {
            $this->directories['cache'] =
                $this->getProjectDir() . '/var/cache/' . $this->getName()
            ;
        }

        return $this->directories['cache'];
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigDir(): string
    {
        if (!isset($this->directories['config'])) {
            $this->directories['config'] = $this->getProjectDir() . '/config';
        }

        return $this->directories['config'];
    }

    /**
     * {@inheritDoc}
     */
    public function getLogDir(): string
    {
        if (!isset($this->directories['log'])) {
            $this->directories['log'] = $this->getProjectDir() . '/var/log';
        }

        return $this->directories['log'];
    }

    /**
     * {@inheritDoc}
     */
    public function getResourcesDir(): string
    {
        if (!isset($this->directories['resources'])) {
            $this->directories['resources'] =
                $this->getProjectDir() . '/resources'
            ;
        }

        return $this->directories['resources'];
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(): array
    {
        return [
            'project_dir' => $this->getProjectDir(),
            'cache_dir' => $this->getCacheDir(),
            'config_dir' => $this->getConfigDir(),
            'log_dir' => $this->getLogDir(),
            'resources_dir' => $this->getResourcesDir(),
            'environment' => $this->getName(),
            'debug' => $this->isDebug(),
            'context' => $this->getContext(),
            'env_vars' => $this->getEnvVars(),
        ];
    }

    /**
     * Loads environment variables from .env files.
     *
     * This method loads variables in the following order:
     * 1. .env.local (always ignored in test env)
     * 2. .env.$environment.local (always ignored in test env)
     * 3. .env.$environment
     * 4. .env
     */
    protected function loadEnvironmentVariables(): void
    {
        $projectDir = $this->getProjectDir();
        $env = $this->getName();

        $dotenv = new Dotenv();

        // Load .env files in order of precedence.
        $envFiles = [
            $projectDir . '/.env.local',
            $projectDir . '/.env.' . $env . '.local',
            $projectDir . '/.env.' . $env,
            $projectDir . '/.env',
        ];

        foreach ($envFiles as $envFile) {
            if (file_exists($envFile)) {
                // Skip .env.local files in test environment.
                if (str_contains($envFile, '.local') && $env === self::TEST) {
                    continue;
                }

                $dotenv->loadEnv($envFile);
            }
        }

        // Store loaded variables.
        $this->envVars = $_ENV;
    }
}
