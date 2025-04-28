<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Kernel\Config\Loader;

use InvalidArgumentException;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads routes configuration from YAML files.
 */
class YamlRoutesLoader extends FileLoader
{
    public function __construct(
        private ContainerBuilder $container,
        protected FileLocatorInterface $locator
    ) {
        parent::__construct($locator);
    }

    /**
     * Loads a YAML route configuration file.
     *
     * @param mixed $resource The resource.
     * @param string|null $type The resource type.
     * @return array The route configuration.
     */
    public function load($resource, ?string $type = null): mixed
    {
        $routes = Yaml::parseFile($resource);

        if (!is_array($routes)) {
            throw new InvalidArgumentException(sprintf(
                'The YAML file "%s" has an invalid type, got %s.',
                $resource,
                get_debug_type($routes)
            ));
        }

        $this->container->setParameter('routes', $routes);

        return $routes;
    }

    /**
     * Checks whether this loader can load the given resource.
     *
     * @param mixed $resource A resource.
     * @param string|null $type The resource type.
     * @return bool True if this loader can load the resource.
     */
    public function supports($resource, ?string $type = null): bool
    {
        return is_string($resource)
            && 'yaml' === pathinfo($resource, PATHINFO_EXTENSION)
            && 'routes' === $type
        ;
    }
}
