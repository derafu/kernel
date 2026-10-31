<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsKernel\Config\Loader;

use Derafu\File\Directory;
use Derafu\Kernel\Config\Loader\PhpRoutesLoader;
use Derafu\Kernel\Config\Loader\YamlRoutesLoader;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(PhpRoutesLoader::class)]
#[CoversClass(YamlRoutesLoader::class)]
final class RoutesValidationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('kernel_routes_validation_', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        Directory::remove($this->directory);
    }

    private function translator(): Translator
    {
        $translator = new Translator('es');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource(
            'php',
            dirname(__DIR__, 4) . '/resources/translations/errors+intl-icu.es.php',
            'es',
            'errors+intl-icu'
        );

        return $translator;
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidImports(): array
    {
        return [
            'string imports' => [
                'imports: routes.yaml',
                'The imports in the YAML file "{file}" must be an array; string given.',
                'Los imports del archivo YAML "{file}" deben ser un arreglo; se recibió string.',
            ],
            'boolean imports' => [
                'imports: false',
                'The imports in the YAML file "{file}" must be an array; bool given.',
                'Los imports del archivo YAML "{file}" deben ser un arreglo; se recibió bool.',
            ],
            'string import' => [
                "imports:\n  named: routes.yaml",
                'The import "named" in the YAML file "{file}" must be an array; string given.',
                'El import "named" del archivo YAML "{file}" debe ser un arreglo; se recibió string.',
            ],
            'null import' => [
                "imports:\n  - null",
                'The import "0" in the YAML file "{file}" must be an array; null given.',
                'El import "0" del archivo YAML "{file}" debe ser un arreglo; se recibió null.',
            ],
            'missing resource' => [
                'imports: [{other: routes.yaml}]',
                'The import "0" in the YAML file "{file}" must define a string resource; null given.',
                'El import "0" del archivo YAML "{file}" debe definir un resource de tipo string; se recibió null.',
            ],
            'null resource' => [
                'imports: [{resource: null}]',
                'The import "0" in the YAML file "{file}" must define a string resource; null given.',
                'El import "0" del archivo YAML "{file}" debe definir un resource de tipo string; se recibió null.',
            ],
            'numeric resource' => [
                'imports: [{resource: 123}]',
                'The import "0" in the YAML file "{file}" must define a string resource; int given.',
                'El import "0" del archivo YAML "{file}" debe definir un resource de tipo string; se recibió int.',
            ],
            'array resource' => [
                'imports: [{resource: [routes.yaml]}]',
                'The import "0" in the YAML file "{file}" must define a string resource; array given.',
                'El import "0" del archivo YAML "{file}" debe definir un resource de tipo string; se recibió array.',
            ],
        ];
    }

    #[DataProvider('invalidImports')]
    public function testInvalidImportsIdentifyTheFileAndEntryInBothLanguages(
        string $yaml,
        string $message,
        string $translation
    ): void {
        $file = $this->directory . '/routes.yaml';
        file_put_contents($file, $yaml);
        $container = new ContainerBuilder();
        $loader = new YamlRoutesLoader($container, new FileLocator($this->directory));

        try {
            $loader->load($file);
            $this->fail('Invalid imports must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(str_replace('{file}', $file, $message), $exception->getMessage());
            $this->assertSame(str_replace('{file}', $file, $translation), $exception->trans($this->translator()));
            $this->assertFalse($container->hasParameter('routes'));
        }
    }

    /** @return array<string, array{string}> */
    public static function formats(): array
    {
        return ['PHP' => ['php'], 'YAML' => ['yaml']];
    }

    /**
     * @param array<array-key, mixed> $routes
     * @return array<array-key, mixed>
     */
    private function loadRoutes(string $format, array $routes, ContainerBuilder $container): array
    {
        $file = $this->directory . '/routes.' . $format;
        file_put_contents(
            $file,
            $format === 'php' ? '<?php return ' . var_export($routes, true) . ';' : Yaml::dump($routes)
        );
        $locator = new FileLocator($this->directory);
        $loader = $format === 'php'
            ? new PhpRoutesLoader($container, $locator)
            : new YamlRoutesLoader($container, $locator);

        return $loader->load($file);
    }

    /** @return iterable<string, array{string, null|bool|int|float|string}> */
    public static function invalidRoutes(): iterable
    {
        foreach (['php', 'yaml'] as $format) {
            foreach ([null, false, true, 123, 1.5, 'controller'] as $value) {
                yield $format . ':' . var_export($value, true) => [$format, $value];
            }
        }
    }

    #[DataProvider('invalidRoutes')]
    public function testEachRouteMustBeAnArray(string $format, mixed $value): void
    {
        $container = new ContainerBuilder();
        try {
            $this->loadRoutes($format, ['broken' => $value], $container);
            $this->fail('A route with an invalid structure must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $type = get_debug_type($value);
            $this->assertSame('The route "broken" must be an array; ' . $type . ' given.', $exception->getMessage());
            $this->assertSame(
                'La ruta "broken" debe ser un arreglo; se recibió ' . $type . '.',
                $exception->trans($this->translator())
            );
            $this->assertFalse($container->hasParameter('routes'));
        }
    }

    #[DataProvider('invalidRoutes')]
    public function testAnInvalidExistingContainerParameterIsNotOverwritten(
        string $format,
        null|bool|int|float|string $value
    ): void {
        $container = new ContainerBuilder();
        $container->setParameter('routes', $value);
        try {
            $this->loadRoutes($format, ['valid' => ['handler' => 'controller']], $container);
            $this->fail('An invalid routes parameter must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $type = get_debug_type($value);
            $this->assertSame('The container parameter "routes" must be an array; ' . $type . ' given.', $exception->getMessage());
            $this->assertSame(
                'El parámetro "routes" del contenedor debe ser un arreglo; se recibió ' . $type . '.',
                $exception->trans($this->translator())
            );
            $this->assertSame($value, $container->getParameter('routes'));
        }
    }

    #[DataProvider('formats')]
    public function testOnlyRedirectStringHandlersAreEscaped(string $format): void
    {
        $routes = [
            'missing' => [],
            'null' => ['handler' => null],
            'false' => ['handler' => false],
            'empty' => ['handler' => ''],
            'number' => ['handler' => 123],
            'array' => ['handler' => ['Controller', 'action']],
            'ordinary' => ['handler' => 'controller:%unchanged%'],
            'redirect' => ['handler' => 'redirect:/search?q=%20', 'path' => '/old'],
        ];
        $expected = $routes;
        $expected['redirect']['handler'] = 'redirect:/search?q=%%20';
        $container = new ContainerBuilder();

        $this->assertSame($expected, $this->loadRoutes($format, $routes, $container));
        $this->assertSame($expected, $container->getParameter('routes'));
    }

    public function testNamedImportsAndExtraFieldsKeepOrderAndMainFilePrecedence(): void
    {
        file_put_contents($this->directory . '/first.yaml', "first: {}\nshared: {handler: first}\n");
        file_put_contents($this->directory . '/second.yaml', "second: {}\nshared: {handler: second}\n");
        $container = new ContainerBuilder();
        $routes = $this->loadRoutes('yaml', [
            'imports' => [
                'first' => ['resource' => 'first.yaml', 'extra' => true],
                7 => ['resource' => 'second.yaml'],
            ],
            'shared' => ['handler' => 'main'],
            'last' => [],
        ], $container);

        $this->assertSame(['first', 'shared', 'second', 'last'], array_keys($routes));
        $this->assertSame(['handler' => 'main'], $routes['shared']);
        $this->assertSame($routes, $container->getParameter('routes'));
    }

    public function testAnEmptyImportsArrayIsAcceptedAndRemoved(): void
    {
        $container = new ContainerBuilder();
        $this->assertSame([], $this->loadRoutes('yaml', ['imports' => []], $container));
        $this->assertSame([], $container->getParameter('routes'));
    }
}
