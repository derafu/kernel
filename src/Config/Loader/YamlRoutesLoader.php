<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Kernel\Config\Loader;

use Derafu\Kernel\Trait\RoutesParameterTrait;
use Derafu\Kernel\Trait\RoutesSanitizerTrait;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads routes configuration from YAML files.
 */
class YamlRoutesLoader extends FileLoader
{
    use RoutesParameterTrait;
    use RoutesSanitizerTrait;

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
     * @return array<array-key, mixed> The route configuration.
     */
    public function load($resource, ?string $type = null): mixed
    {
        if (!is_string($resource)) {
            throw new InvalidArgumentException([
                'The YAML routes resource must be a string; {type} given.',
                'type' => get_debug_type($resource),
            ]);
        }

        // Load main routes.
        $routes = Yaml::parseFile($resource);

        // Check if the routes are an array.
        if (!is_array($routes)) {
            throw new InvalidArgumentException([
                'The YAML file "{file}" has an invalid type, got {type}.',
                'file' => $resource,
                'type' => get_debug_type($routes),
            ]);
        }

        // Load imported routes.
        if (isset($routes['imports'])) {
            if (!is_array($routes['imports'])) {
                throw new InvalidArgumentException([
                    'The imports in the YAML file "{file}" must be an array; {type} given.',
                    'file' => $resource,
                    'type' => get_debug_type($routes['imports']),
                ]);
            }

            $importedRoutes = [];
            foreach ($routes['imports'] as $name => $import) {
                if (!is_array($import)) {
                    throw new InvalidArgumentException([
                        'The import "{name}" in the YAML file "{file}" must be an array; {type} given.',
                        'name' => $name,
                        'file' => $resource,
                        'type' => get_debug_type($import),
                    ]);
                }

                if (!isset($import['resource']) || !is_string($import['resource'])) {
                    throw new InvalidArgumentException([
                        'The import "{name}" in the YAML file "{file}" must define a string resource; {type} given.',
                        'name' => $name,
                        'file' => $resource,
                        'type' => get_debug_type($import['resource'] ?? null),
                    ]);
                }

                $importedRoutes = array_merge(
                    $importedRoutes,
                    $this->load(
                        dirname($resource) . '/' . $import['resource'],
                        $type
                    )
                );
            }
            unset($routes['imports']);
            $routes = array_merge($importedRoutes, $routes);
        }

        // Set routes parameter.
        $routes = $this->sanitizeRoutes($routes);
        $this->addToRoutesParameter($routes);

        // Return routes.
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
