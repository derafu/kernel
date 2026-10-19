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

use Derafu\Kernel\Config\Loader\PhpRoutesLoader;
use Derafu\Kernel\Environment;
use Derafu\Kernel\MicroKernel;
use Derafu\Support\File;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(Environment::class)]
#[UsesClass(MicroKernel::class)]
#[UsesClass(PhpRoutesLoader::class)]
final class EnvironmentValuesTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $env;

    /** @var array<string, mixed> */
    private array $server;

    private string $directory;

    protected function setUp(): void
    {
        $this->env = $_ENV;
        $this->server = $_SERVER;
        $_ENV = [];
        unset($_SERVER['SYMFONY_DOTENV_VARS']);
        $this->directory = sys_get_temp_dir() . '/' . uniqid('kernel_env_values_', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->env;
        $_SERVER = $this->server;
        File::rmdir($this->directory);
    }

    private function environment(): Environment
    {
        return new Environment('test', true, [], [
            'project' => $this->directory,
            'config' => $this->directory,
            'cache' => $this->directory . '/cache',
        ]);
    }

    /** @return array<string, array{mixed}> */
    public static function allowedValues(): array
    {
        return [
            'string' => ['text'],
            'empty string' => [''],
            'integer' => [42],
            'zero' => [0],
            'float' => [3.14],
            'true' => [true],
            'false' => [false],
            'null' => [null],
            'empty array' => [[]],
            'nested arrays' => [['database' => ['port' => 5432, 'flags' => [true, null, '']]]],
        ];
    }

    #[DataProvider('allowedValues')]
    public function testAllowedValuesKeepTheirTypesInTheSnapshotAndExport(mixed $value): void
    {
        $_ENV['KERNEL_ENV_VALUE'] = $value;
        $environment = $this->environment();

        $this->assertSame(['KERNEL_ENV_VALUE' => $value], $environment->getEnvVars());
        $this->assertSame(['KERNEL_ENV_VALUE' => $value], $environment->toArray()['env_vars']);
        $this->assertSame(['KERNEL_ENV_VALUE' => $value], $_ENV);
    }

    public function testAnEmptyEnvironmentRemainsEmpty(): void
    {
        $this->assertSame([], $this->environment()->getEnvVars());
    }

    public function testIntegerAndStringKeysArePreservedAtEveryLevel(): void
    {
        $values = [7 => 'seven', 'SETTINGS' => [4 => null, 'flags' => [false, true, 0.0]]];
        $_ENV = $values;

        $this->assertSame($values, $this->environment()->getEnvVars());
    }

    public function testValidationDoesNotImposeAFixedNestingDepth(): void
    {
        $value = 'leaf';
        for ($depth = 0; $depth < 64; $depth++) {
            $value = ['next' => $value];
        }
        $_ENV['KERNEL_ENV_VALUE'] = $value;

        $this->assertSame(['KERNEL_ENV_VALUE' => $value], $this->environment()->getEnvVars());
    }

    public function testTheCapturedArraysAreIndependentOfLaterGlobalChanges(): void
    {
        $_ENV['SETTINGS'] = ['database' => ['port' => 5432]];
        $environment = $this->environment();
        $this->assertIsArray($_ENV['SETTINGS']);
        $this->assertIsArray($_ENV['SETTINGS']['database']);
        $_ENV['SETTINGS']['database']['port'] = 3306;

        $this->assertSame(['SETTINGS' => ['database' => ['port' => 5432]]], $environment->getEnvVars());
        $this->assertSame(['database' => ['port' => 5432]], $environment->getEnv('SETTINGS'));
    }

    public function testDotenvAddsStringsAndPreservesPreviouslyTypedValues(): void
    {
        $_ENV['KERNEL_ENV_VALUE'] = 8080;
        unset($_SERVER['KERNEL_ENV_VALUE'], $_SERVER['KERNEL_DOTENV_FLAG'], $_SERVER['KERNEL_DOTENV_JSON']);
        file_put_contents($this->directory . '/.env', "KERNEL_ENV_VALUE=9090\nKERNEL_DOTENV_FLAG=false\nKERNEL_DOTENV_JSON='[1,true]'\n");
        $environment = $this->environment();
        $values = $environment->getEnvVars();

        $this->assertSame(8080, $values['KERNEL_ENV_VALUE']);
        $this->assertSame('false', $values['KERNEL_DOTENV_FLAG']);
        $this->assertSame('[1,true]', $values['KERNEL_DOTENV_JSON']);
        $this->assertFalse($environment->getEnv('bool:KERNEL_DOTENV_FLAG'));
        $this->assertSame([1, true], $environment->getEnv('json:KERNEL_DOTENV_JSON'));
    }

    public function testTheCompiledContainerPreservesTypesAndLiteralPercentSigns(): void
    {
        $values = [
            'KERNEL_INT' => 42,
            'KERNEL_BOOL' => false,
            'KERNEL_NULL' => null,
            'KERNEL_ARRAY' => ['port' => 5432, 'literal' => '%missing_parameter%', 'flags' => [true, null]],
        ];
        $_ENV = $values;
        $environment = $this->environment();
        $kernel = new ValuesKernel($environment, false, uniqid('kernel_env_'));
        $container = $kernel->container();

        foreach ($values as $name => $value) {
            $this->assertSame($value, $container->getParameter('env.' . $name), $name);
        }
        $this->assertSame($values, $environment->getEnvVars());
        $this->assertSame($values, $_ENV);
    }

    /** @return Generator<string, array{array<array-key, mixed>, string, string}> */
    public static function invalidObjects(): Generator
    {
        foreach ([new stdClass(), new EnvironmentStringValue(), EnvironmentEnumValue::Example] as $object) {
            $type = get_debug_type($object);
            yield $type . ':root' => [['SETTINGS' => $object], '$_ENV[SETTINGS]', $type];
            yield $type . ':nested' => [
                ['SETTINGS' => ['database' => ['connection' => $object]]],
                '$_ENV[SETTINGS][database][connection]',
                $type,
            ];
            yield $type . ':numeric key' => [
                ['SETTINGS' => ['connections' => [0 => $object]]],
                '$_ENV[SETTINGS][connections][0]',
                $type,
            ];
        }
    }

    /** @param array<array-key, mixed> $values */
    #[DataProvider('invalidObjects')]
    public function testObjectsAreRejectedAtAnyLevelWithATranslatablePath(
        array $values,
        string $path,
        string $type
    ): void {
        $this->assertRejectedValues($values, $path, $type);
        $this->assertSame($values, $_ENV);
    }

    /** @return array<string, array{bool, bool}> */
    public static function resources(): array
    {
        return [
            'open at root' => [false, false],
            'open nested' => [false, true],
            'closed at root' => [true, false],
            'closed nested' => [true, true],
        ];
    }

    #[DataProvider('resources')]
    public function testOpenAndClosedResourcesAreRejected(bool $closed, bool $nested): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        if ($closed) {
            fclose($resource);
        }
        try {
            $values = $nested ? ['SETTINGS' => ['stream' => $resource]] : ['SETTINGS' => $resource];
            $path = $nested ? '$_ENV[SETTINGS][stream]' : '$_ENV[SETTINGS]';
            $this->assertRejectedValues($values, $path, get_debug_type($resource));
            if (!$closed) {
                $this->assertSame(2, fwrite($resource, 'ok'));
            }
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    /** @param array<array-key, mixed> $values */
    private function assertRejectedValues(array $values, string $path, string $type): void
    {
        $_ENV = $values;
        try {
            $this->environment();
            $this->fail('Objects and resources must not be captured as environment values.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'The environment variable ' . $path . ' must be a scalar or null; ' . $type . ' given.',
                $exception->getMessage()
            );
            $this->assertSame(['name' => $path, 'type' => $type], $exception->getTranslatableMessage()->getParameters());
            $translator = new Translator('es');
            $translator->addLoader('php', new PhpFileLoader());
            $translator->addResource(
                'php',
                dirname(__DIR__, 2) . '/resources/translations/errors+intl-icu.es.php',
                'es',
                'errors+intl-icu'
            );
            $this->assertSame(
                'La variable de entorno ' . $path . ' debe ser un escalar o null; se recibió ' . $type . '.',
                $exception->trans($translator)
            );
        }
    }
}

final class ValuesKernel extends MicroKernel
{
    protected const CONFIG_FILES = [];

    public function container(): ContainerInterface
    {
        return $this->getContainer();
    }
}

final class EnvironmentStringValue implements Stringable
{
    public function __toString(): string
    {
        return 'text';
    }
}

enum EnvironmentEnumValue
{
    case Example;
}
