<?php

declare(strict_types=1);

/**
 * Derafu: Kernel - Lightweight Kernel Implementation with Container.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsKernel;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;
use Derafu\Kernel\Environment;
use Derafu\Support\File;
use Derafu\Translation\Exception\Core\TranslatableRuntimeException as RuntimeException;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(Environment::class)]
final class EnvironmentProjectDirValidationTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $env;

    /** @var array<string, mixed> */
    private array $server;

    private string $directory;

    private ?ClassLoader $loader = null;

    protected function setUp(): void
    {
        $this->env = $_ENV;
        $this->server = $_SERVER;
        $temporaryDirectory = realpath(sys_get_temp_dir());
        $this->assertIsString($temporaryDirectory);
        $this->directory = $temporaryDirectory . '/' . uniqid('kernel_project_dir_', true);
        mkdir($this->directory . '/vendor/composer', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->loader?->unregister();
        $_ENV = $this->env;
        $_SERVER = $this->server;
        File::rmdir($this->directory);
    }

    /**
     * Registers a separate Composer installation using its real public API.
     */
    private function useComposerRoot(string $path): void
    {
        $data = [
            'root' => [
                'name' => 'test/project',
                'pretty_version' => '1.0.0',
                'version' => '1.0.0.0',
                'reference' => null,
                'type' => 'project',
                'install_path' => $path,
                'aliases' => [],
                'dev' => true,
            ],
            'versions' => [],
        ];
        file_put_contents(
            $this->directory . '/vendor/composer/installed.php',
            '<?php return ' . var_export($data, true) . ';'
        );
        $this->loader = new ClassLoader($this->directory . '/vendor');
        $this->loader->register(true);
        $this->assertSame($path, InstalledVersions::getRootPackage()['install_path']);
    }

    private function translator(): Translator
    {
        $translator = new Translator('es');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource(
            'php',
            dirname(__DIR__, 2) . '/resources/translations/errors+intl-icu.es.php',
            'es',
            'errors+intl-icu'
        );

        return $translator;
    }

    /** @return array<string, array{mixed}> */
    public static function invalidContextValues(): array
    {
        return [
            'false' => [false],
            'true' => [true],
            'integer' => [0],
            'float' => [3.14],
            'empty array' => [[]],
            'array' => [['path' => '/project']],
            'object' => [new stdClass()],
            'stringable object' => [new ProjectDirectoryStringValue()],
        ];
    }

    #[DataProvider('invalidContextValues')]
    public function testInvalidContextDirectoryProducesATranslatableError(mixed $value): void
    {
        try {
            new Environment('test', false, ['PROJECT_DIR' => $value]);
            $this->fail('An invalid context directory must not be converted to string.');
        } catch (InvalidArgumentException $exception) {
            $type = get_debug_type($value);
            $this->assertSame(
                'The PROJECT_DIR context value must be a string; ' . $type . ' given.',
                $exception->getMessage()
            );
            $this->assertSame(['type' => $type], $exception->getTranslatableMessage()->getParameters());
            $this->assertSame(
                'El valor PROJECT_DIR del contexto debe ser un string; se recibió ' . $type . '.',
                $exception->trans($this->translator())
            );
        }
    }

    #[DataProvider('invalidContextValues')]
    public function testAnExplicitDirectoryStillTakesPriorityOverInvalidContext(mixed $value): void
    {
        $directory = $this->directory . '/explicit';
        $environment = new Environment('test', false, ['PROJECT_DIR' => $value], ['project' => $directory]);

        $this->assertSame($directory, $environment->getProjectDir());
        $this->assertSame($directory . '/config', $environment->getConfigDir());
    }

    public function testAResourceIsRejectedWithoutClosingIt(): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        try {
            try {
                new Environment('test', false, ['PROJECT_DIR' => $resource]);
                $this->fail('A resource cannot be a project directory.');
            } catch (InvalidArgumentException $exception) {
                $this->assertSame(
                    'The PROJECT_DIR context value must be a string; resource (stream) given.',
                    $exception->getMessage()
                );
                $this->assertSame(2, fwrite($resource, 'ok'));
            }
        } finally {
            fclose($resource);
        }
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function missingContextValues(): array
    {
        return ['missing' => [[]], 'null' => [['PROJECT_DIR' => null]]];
    }

    /** @param array<string, mixed> $context */
    #[DataProvider('missingContextValues')]
    public function testComposerFallbackResolvesParentSegmentsToTheActualProject(array $context): void
    {
        mkdir($this->directory . '/project');
        $this->useComposerRoot($this->directory . '/project/../project');
        $environment = new Environment('test', false, $context);

        $this->assertSame($this->directory . '/project', $environment->getProjectDir());
        $this->assertSame($this->directory . '/project/var/cache/test', $environment->getCacheDir());
    }

    public function testContextStringsArePreservedWithoutCheckingTheirExistence(): void
    {
        $path = $this->directory . '/missing/../project';
        $environment = new Environment('test', false, ['PROJECT_DIR' => $path]);

        $this->assertSame($path, $environment->getProjectDir());
    }

    public function testAnEmptyContextStringStillTakesPriorityOverComposer(): void
    {
        $environment = new Environment('test', false, ['PROJECT_DIR' => '']);

        $this->assertSame('', $environment->getProjectDir());
    }

    public function testAnUnresolvableComposerDirectoryProducesATranslatableError(): void
    {
        $path = $this->directory . '/missing-project';
        $this->useComposerRoot($path);

        try {
            new Environment('test');
            $this->fail('An unresolvable Composer directory must not become an empty string.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'The Composer project directory "' . $path . '" could not be resolved.',
                $exception->getMessage()
            );
            $this->assertSame(['path' => $path], $exception->getTranslatableMessage()->getParameters());
            $this->assertSame(
                'No se pudo resolver el directorio del proyecto Composer "' . $path . '".',
                $exception->trans($this->translator())
            );
        }
    }

    public function testAnExplicitDirectoryBypassesAnUnresolvableComposerRoot(): void
    {
        $this->useComposerRoot($this->directory . '/missing-project');
        $environment = new Environment('test', false, [], ['project' => $this->directory]);

        $this->assertSame($this->directory, $environment->getProjectDir());
    }

    public function testAContextDirectoryBypassesAnUnresolvableComposerRoot(): void
    {
        $this->useComposerRoot($this->directory . '/missing-project');
        $environment = new Environment('test', false, ['PROJECT_DIR' => $this->directory]);

        $this->assertSame($this->directory, $environment->getProjectDir());
    }
}

final class ProjectDirectoryStringValue implements Stringable
{
    public function __toString(): string
    {
        return '/project';
    }
}
