<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'The PROJECT_DIR context value must be a string; {type} given.' =>
        'El valor PROJECT_DIR del contexto debe ser un string; se recibió {type}.',
    'The Composer project directory "{path}" could not be resolved.' =>
        'No se pudo resolver el directorio del proyecto Composer "{path}".',
    'The environment variable {name} must be a scalar or null; {type} given.' =>
        'La variable de entorno {name} debe ser un escalar o null; se recibió {type}.',
    'The PHP file "{file}" must return an array, got {type}.' =>
        'El archivo PHP "{file}" debe retornar un arreglo, pero retornó {type}.',
    'The YAML file "{file}" has an invalid type, got {type}.' =>
        'El archivo YAML "{file}" tiene un tipo inválido, se obtuvo {type}.',
    'The YAML routes resource must be a string; {type} given.' =>
        'El recurso de rutas YAML debe ser un string; se recibió {type}.',
    'The imports in the YAML file "{file}" must be an array; {type} given.' =>
        'Los imports del archivo YAML "{file}" deben ser un arreglo; se recibió {type}.',
    'The import "{name}" in the YAML file "{file}" must be an array; {type} given.' =>
        'El import "{name}" del archivo YAML "{file}" debe ser un arreglo; se recibió {type}.',
    'The import "{name}" in the YAML file "{file}" must define a string resource; {type} given.' =>
        'El import "{name}" del archivo YAML "{file}" debe definir un resource de tipo string; se recibió {type}.',
    'The container parameter "routes" must be an array; {type} given.' =>
        'El parámetro "routes" del contenedor debe ser un arreglo; se recibió {type}.',
    'The route "{name}" must be an array; {type} given.' =>
        'La ruta "{name}" debe ser un arreglo; se recibió {type}.',
];
