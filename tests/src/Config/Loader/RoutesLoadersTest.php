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
use Derafu\Translation\Contract\TranslatableInterface;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Throwable;

/**
 * A routes file that does not have the expected content is a translatable
 * error that says which file it is and what it has.
 */
#[CoversClass(PhpRoutesLoader::class)]
#[CoversClass(YamlRoutesLoader::class)]
final class RoutesLoadersTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/derafu-kernel-' . uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        Directory::remove($this->directory);
    }

    private function assertTranslatableError(callable $load, string $message): void
    {
        $exception = null;
        try {
            $load();
        } catch (Throwable $e) {
            $exception = $e;
        }

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(TranslatableInterface::class, $exception);
        $this->assertSame($message, $exception->getMessage());
    }

    public function testAPhpFileThatDoesNotReturnAnArrayIsATranslatableError(): void
    {
        $file = $this->directory . '/routes.php';
        file_put_contents($file, '<?php return "no";');

        $loader = new PhpRoutesLoader(new ContainerBuilder(), new FileLocator($this->directory));

        $this->assertTranslatableError(
            fn () => $loader->load($file),
            sprintf('The PHP file "%s" must return an array, got string.', $file)
        );
    }

    public function testAYamlFileThatIsNotAnArrayIsATranslatableError(): void
    {
        $file = $this->directory . '/routes.yaml';
        file_put_contents($file, 'just text');

        $loader = new YamlRoutesLoader(new ContainerBuilder(), new FileLocator($this->directory));

        $this->assertTranslatableError(
            fn () => $loader->load($file),
            sprintf('The YAML file "%s" has an invalid type, got string.', $file)
        );
    }

    /** @return array<string, array{mixed}> */
    public static function invalidYamlResources(): array
    {
        return [
            'null' => [null],
            'false' => [false],
            'true' => [true],
            'integer' => [123],
            'float' => [1.5],
            'empty array' => [[]],
            'array with path' => [['resource' => 'routes.yaml']],
            'object' => [new stdClass()],
            'stringable object' => [new class () implements Stringable {
                public function __toString(): string
                {
                    return 'routes.yaml';
                }
            }],
        ];
    }

    #[DataProvider('invalidYamlResources')]
    public function testAYamlResourceMustBeAString(mixed $resource): void
    {
        $loader = new YamlRoutesLoader(new ContainerBuilder(), new FileLocator($this->directory));

        $this->assertTranslatableError(
            fn () => $loader->load($resource),
            'The YAML routes resource must be a string; ' . get_debug_type($resource) . ' given.'
        );
    }

    public function testAStreamCannotBeAYamlPathAndRemainsOpen(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        try {
            $loader = new YamlRoutesLoader(new ContainerBuilder(), new FileLocator($this->directory));
            $this->assertTranslatableError(
                fn () => $loader->load($resource),
                'The YAML routes resource must be a string; resource (stream) given.'
            );
            $this->assertSame(2, fwrite($resource, 'ok'));
        } finally {
            fclose($resource);
        }
    }

    public function testAnInvalidYamlResourceErrorIsTranslatedIntoSpanish(): void
    {
        $translator = new Translator('es');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource(
            'php',
            dirname(__DIR__, 4) . '/resources/translations/errors+intl-icu.es.php',
            'es',
            'errors+intl-icu'
        );
        $loader = new YamlRoutesLoader(new ContainerBuilder(), new FileLocator($this->directory));

        try {
            $loader->load(null);
            $this->fail('A null YAML resource must be rejected.');
        } catch (TranslatableInvalidArgumentException $exception) {
            $this->assertSame(['type' => 'null'], $exception->getTranslatableMessage()->getParameters());
            $this->assertSame(
                'El recurso de rutas YAML debe ser un string; se recibió null.',
                $exception->trans($translator)
            );
        }
    }
}
