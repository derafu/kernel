<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Kernel\Contract;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interface for a very small kernel.
 */
interface KernelInterface
{
    /**
     * Gets the environment the kernel is running in.
     *
     * @return string
     */
    public function getEnvironment(): string;

    /**
     * Checks if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebug(): bool;

    /**
     * Gets the container used by the kernel.
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface;

    /**
     * Gets the project root directory.
     *
     * @return string
     */
    public function getProjectDir(): string;

    /**
     * Gets the build directory.
     *
     * @return string
     */
    public function getBuildDir(): string;

    /**
     * Gets the cache directory.
     *
     * @return string
     */
    public function getCacheDir(): string;

    /**
     * Gets the config directory.
     *
     * @return string
     */
    public function getConfigDir(): string;

    /**
     * Gets the log directory.
     *
     * @return string
     */
    public function getLogDir(): string;

    /**
     * Gets the resources directory.
     *
     * @return string The resources directory path.
     */
    public function getResourcesDir(): string;

    /**
     * Gets the translations directory.
     *
     * @return string The translations directory path.
     */
    public function getTranslationsDir(): string;

    /**
     * Gets the templates directory.
     *
     * @return string The templates directory path.
     */
    public function getTemplatesDir(): string;

    /**
     * Boots the kernel.
     *
     * @return void
     */
    public function boot(): void;
}
