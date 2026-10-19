<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Kernel\Trait;

use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;

/**
 * Trait to sanitize routes.
 */
trait RoutesSanitizerTrait
{
    /**
     * Sanitize routes.
     *
     *   - Replace % with %% in the handler if it is a redirect route.
     *
     * @param array<array-key, mixed> $routes The routes to sanitize.
     * @return array<array-key, mixed> The sanitized routes.
     */
    protected function sanitizeRoutes(array $routes): array
    {
        foreach ($routes as $name => &$route) {
            if (!is_array($route)) {
                throw new InvalidArgumentException([
                    'The route "{name}" must be an array; {type} given.',
                    'name' => $name,
                    'type' => get_debug_type($route),
                ]);
            }

            if (
                !empty($route['handler'])
                && is_string($route['handler'])
                && str_starts_with($route['handler'], 'redirect:')
            ) {
                $route['handler'] = str_replace('%', '%%', $route['handler']);
            }
        }

        return $routes;
    }
}
