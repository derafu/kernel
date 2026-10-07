<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Kernel\Trait;

/**
 * Trait to add the routes of a file to the `routes` parameter of the container.
 *
 * The user needs a `$container` property with the `ContainerBuilder`.
 */
trait RoutesParameterTrait
{
    /**
     * Adds routes to the `routes` parameter, after the ones that are there.
     *
     * Each file of routes is loaded after the one before it, so the routes of
     * all of them are kept (a `routes.yaml` and a `routes.php` do not take each
     * other's away), in the order the files were loaded. A name that is already
     * there keeps its place in the order and gets the route of the later file.
     *
     * @param array<string, mixed> $routes The routes of the file.
     */
    protected function addToRoutesParameter(array $routes): void
    {
        $current = $this->container->hasParameter('routes')
            ? $this->container->getParameter('routes')
            : []
        ;

        $this->container->setParameter('routes', array_merge($current, $routes));
    }
}
