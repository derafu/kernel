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
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
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
     * Environment values captured after loading the .env files.
     *
     * Arrays recursively contain only scalars, null, or other arrays.
     * Objects and resources are rejected at every nesting level.
     *
     * @var array<array-key, scalar|array<array-key, mixed>|null>
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
     *
     * @throws InvalidArgumentException If a typed conversion receives a non-scalar value.
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
            'int' => (int) $this->requireScalarEnvValue($value, $name),
            'float' => (float) $this->requireScalarEnvValue($value, $name),
            'json' => json_decode(
                (string) $this->requireScalarEnvValue($value, $name),
                true
            ),
            default => $value,
        };
    }

    /**
     * Requires a scalar or null without changing the value to be converted.
     *
     * @throws InvalidArgumentException If the value is not scalar or null.
     */
    private function requireScalarEnvValue(
        mixed $value,
        string $name
    ): bool|int|float|string|null {
        if ($value !== null && !is_scalar($value)) {
            throw new InvalidArgumentException([
                'The environment variable {name} must be a scalar or null; {type} given.',
                'name' => $name,
                'type' => get_debug_type($value),
            ]);
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException If the PROJECT_DIR context value is not a string.
     * @throws RuntimeException If the Composer project directory cannot be resolved.
     */
    public function getProjectDir(): string
    {
        if (!isset($this->directories['project'])) {
            // The `PROJECT_DIR` of the context, if the application gives it
            // (the context is what it receives from the runtime); if not, the
            // root package of Composer, which is the project that is running.
            $projectDir = $this->context['PROJECT_DIR'] ?? null;
            if ($projectDir !== null && !is_string($projectDir)) {
                throw new InvalidArgumentException([
                    'The PROJECT_DIR context value must be a string; {type} given.',
                    'type' => get_debug_type($projectDir),
                ]);
            }

            if ($projectDir === null) {
                $installPath = InstalledVersions::getRootPackage()['install_path'];
                $projectDir = realpath($installPath);
                if ($projectDir === false) {
                    throw new RuntimeException([
                        'The Composer project directory "{path}" could not be resolved.',
                        'path' => $installPath,
                    ]);
                }
            }

            $this->directories['project'] = $projectDir;
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
     * The files are loaded in this order, by the name of the environment of
     * the kernel, each one replacing the variables that the ones before it
     * defined:
     *
     *   1. `.env`.
     *   2. `.env.<environment>`.
     *   3. `.env.<environment>.local` (not in `test`).
     *   4. `.env.local` (not in `test`).
     *
     * So the last one that has a variable wins. A variable of the real
     * environment (the process, the web server) is never replaced by a file.
     * `APP_ENV` is not defined if nobody defines it.
     */
    protected function loadEnvironmentVariables(): void
    {
        $projectDir = $this->getProjectDir();
        $env = $this->getName();

        $dotenv = new Dotenv();

        // Only the files that are listed are loaded: `Dotenv::loadEnv()` would
        // also load the ones of the `APP_ENV` that it finds (`dev` by default),
        // not the ones of the environment of the kernel, and `.local` files in
        // `test`. `Dotenv::load()` does not replace the variables of the real
        // environment, but it does replace the ones that it loaded before.
        $envFiles = [
            '.env',
            '.env.' . $env,
            '.env.' . $env . '.local',
            '.env.local',
        ];

        foreach ($envFiles as $envFile) {
            // The `.local` files are not loaded in `test`, so the machine of
            // who runs the tests does not change them.
            if ($env === self::TEST && str_ends_with($envFile, '.local')) {
                continue;
            }

            $path = $projectDir . '/' . $envFile;
            if (file_exists($path)) {
                $dotenv->load($path);
            }
        }

        // Validate and capture the values without converting their types.
        $this->envVars = $this->validateEnvironmentValues($_ENV);
    }

    /**
     * Validates environment values recursively without converting them.
     *
     * @param array<array-key, mixed> $values
     * @param string $path The path used to identify an invalid value.
     * @return array<array-key, scalar|array<array-key, mixed>|null>
     * @throws InvalidArgumentException If an object or resource is found.
     */
    private function validateEnvironmentValues(
        array $values,
        string $path = '$_ENV'
    ): array {
        $validated = [];
        foreach ($values as $name => $value) {
            $valuePath = $path . '[' . $name . ']';
            $validated[$name] = is_array($value)
                ? $this->validateEnvironmentValues($value, $valuePath)
                : $this->requireScalarEnvValue($value, $valuePath);
        }

        return $validated;
    }
}
