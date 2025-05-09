<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Kernel;

use Composer\InstalledVersions;
use Derafu\Kernel\Contract\EnvironmentInterface;

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
     * The current environment (e.g., 'dev', 'prod').
     *
     * @var string
     */
    protected string $name;

    /**
     * Whether debug mode is enabled.
     *
     * @var bool
     */
    protected bool $debug;

    /**
     * Context of the environment.
     *
     * @var array
     */
    protected array $context;

    /**
     * The project's root directory path.
     *
     * @var string
     */
    protected string $projectDir;

    /**
     * Creates a new Environment instance.
     *
     * @param string $name The environment name (e.g., 'dev', 'prod').
     * @param bool $debug Whether to enable debug mode.
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $name,
        bool $debug = false,
        array $context = []
    ) {
        $this->name = $name;
        $this->debug = $debug;
        $this->context = $context;
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
    public function getProjectDir(): string
    {
        if (!isset($this->projectDir)) {
            $routingPackagePath = realpath(
                InstalledVersions::getInstallPath('symfony/dependency-injection')
            );
            $this->projectDir = dirname($routingPackagePath, 3);
        }

        return $this->projectDir;
    }

    /**
     * {@inheritDoc}
     */
    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigDir(): string
    {
        return $this->getProjectDir() . '/config';
    }

    /**
     * {@inheritDoc}
     */
    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }

    /**
     * {@inheritDoc}
     */
    public function getResourcesDir(): string
    {
        return $this->getProjectDir() . '/resources';
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
        ];
    }
}
