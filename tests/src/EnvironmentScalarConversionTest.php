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

use Derafu\Kernel\Environment;
use Derafu\Translation\Exception\Logic\TranslatableInvalidArgumentException as InvalidArgumentException;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(Environment::class)]
final class EnvironmentScalarConversionTest extends TestCase
{
    private const VARIABLE = 'DERAFU_KERNEL_SCALAR_TEST_VALUE';

    /** @var array<string, mixed> */
    private array $env;

    /** @var array<string, mixed> */
    private array $server;

    private string|false $processValue;

    protected function setUp(): void
    {
        $this->env = $_ENV;
        $this->server = $_SERVER;
        $this->processValue = getenv(self::VARIABLE);
        unset($_ENV[self::VARIABLE], $_SERVER[self::VARIABLE]);
        putenv(self::VARIABLE);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->env;
        $_SERVER = $this->server;
        putenv($this->processValue === false ? self::VARIABLE : self::VARIABLE . '=' . $this->processValue);
    }

    private function environment(): Environment
    {
        return new Environment('test', false, [], ['project' => dirname(__DIR__) . '/fixtures']);
    }

    /**
     * @return array<string, array{string, bool|int|float|string|null, mixed}>
     */
    public static function scalarConversions(): array
    {
        return [
            'int numeric string' => ['int', '42', 42],
            'int nonnumeric string preserves cast' => ['int', 'invalid', 0],
            'int true' => ['int', true, 1],
            'int false' => ['int', false, 0],
            'int float' => ['int', 3.9, 3],
            'int integer' => ['int', 7, 7],
            'int null' => ['int', null, 0],
            'float numeric string' => ['float', '3.14', 3.14],
            'float nonnumeric string preserves cast' => ['float', 'invalid', 0.0],
            'float integer' => ['float', 7, 7.0],
            'float true' => ['float', true, 1.0],
            'float false' => ['float', false, 0.0],
            'float null' => ['float', null, 0.0],
            'json object' => ['json', '{"key":"value"}', ['key' => 'value']],
            'json list' => ['json', '[1,true]', [1, true]],
            'json boolean' => ['json', 'false', false],
            'json null string' => ['json', 'null', null],
            'json invalid remains null' => ['json', 'invalid', null],
            'json integer' => ['json', 42, 42],
            'json float' => ['json', 3.5, 3.5],
            'json true preserves string cast' => ['json', true, 1],
            'json false preserves string cast' => ['json', false, null],
            'json null preserves string cast' => ['json', null, null],
        ];
    }

    #[DataProvider('scalarConversions')]
    public function testScalarAndNullDefaultsKeepTheirExistingConversions(
        string $prefix,
        bool|int|float|string|null $value,
        mixed $expected
    ): void {
        $this->assertSame($expected, $this->environment()->getEnv($prefix . ':' . self::VARIABLE, $value));
    }

    /** @return array<string, array{string}> */
    public static function prefixes(): array
    {
        return ['integer' => ['int'], 'floating point' => ['float'], 'json' => ['json']];
    }

    /** @return Generator<string, array{string, mixed}> */
    public static function invalidValues(): Generator
    {
        foreach (['int', 'float', 'json'] as $prefix) {
            foreach ([[], [1], new stdClass(), new ScalarConversionStringValue()] as $index => $value) {
                yield $prefix . ':' . $index => [$prefix, $value];
            }
        }
    }

    #[DataProvider('invalidValues')]
    public function testNonScalarDefaultsProduceATranslatableError(string $prefix, mixed $value): void
    {
        $this->assertRejectedValue($this->environment(), $prefix, $value);
    }

    #[DataProvider('prefixes')]
    public function testResourcesAreRejectedAndRemainOpenForTheCaller(string $prefix): void
    {
        $resource = fopen('php://memory', 'r+');
        $this->assertIsResource($resource);
        try {
            $this->assertRejectedValue($this->environment(), $prefix, $resource);
            $this->assertSame(2, fwrite($resource, 'ok'));
            rewind($resource);
            $this->assertSame('ok', stream_get_contents($resource));
        } finally {
            fclose($resource);
        }
    }

    private function assertRejectedValue(
        Environment $environment,
        string $prefix,
        mixed $value,
        ?string $expectedType = null
    ): void {
        try {
            $environment->getEnv($prefix . ':' . self::VARIABLE, $value);
            $this->fail('A non-scalar value must not reach the cast.');
        } catch (InvalidArgumentException $exception) {
            $type = $expectedType ?? get_debug_type($value);
            $this->assertSame(
                'The environment variable ' . self::VARIABLE . ' must be a scalar or null; ' . $type . ' given.',
                $exception->getMessage()
            );
            $this->assertSame([
                'name' => self::VARIABLE,
                'type' => $type,
            ], $exception->getTranslatableMessage()->getParameters());
            $translator = new Translator('es');
            $translator->addLoader('php', new PhpFileLoader());
            $translator->addResource(
                'php',
                dirname(__DIR__, 2) . '/resources/translations/errors+intl-icu.es.php',
                'es',
                'errors+intl-icu'
            );
            $this->assertSame(
                'La variable de entorno ' . self::VARIABLE . ' debe ser un escalar o null; se recibió ' . $type . '.',
                $exception->trans($translator)
            );
        }
    }

    public function testRawAndUnknownPrefixesStillReturnNonScalarDefaultsIntact(): void
    {
        $environment = $this->environment();
        foreach ([[1], new stdClass()] as $value) {
            $this->assertSame($value, $environment->getEnv(self::VARIABLE, $value));
            $this->assertSame($value, $environment->getEnv('unknown:' . self::VARIABLE, $value));
        }
    }

    public function testBooleanConversionStillUsesFilterVarWithoutTheNewRestriction(): void
    {
        $environment = $this->environment();
        $this->assertTrue($environment->getEnv('bool:' . self::VARIABLE, 'true'));
        $this->assertFalse($environment->getEnv('bool:' . self::VARIABLE, 'false'));
        $this->assertFalse($environment->getEnv('bool:' . self::VARIABLE, []));
    }

    public function testTheLoadedSnapshotStillTakesPrecedence(): void
    {
        $_ENV[self::VARIABLE] = '41';
        $environment = $this->environment();
        $_ENV[self::VARIABLE] = '42';
        $_SERVER[self::VARIABLE] = '43';
        putenv(self::VARIABLE . '=44');

        $this->assertSame(41, $environment->getEnv('int:' . self::VARIABLE, 45));
    }

    public function testInvalidValuesFromEnvAndServerAreCheckedToo(): void
    {
        $environment = $this->environment();
        $_ENV[self::VARIABLE] = [];
        $this->assertRejectedValue($environment, 'int', 'unused default', 'array');
        unset($_ENV[self::VARIABLE]);
        $_SERVER[self::VARIABLE] = [];
        $this->assertRejectedValue($environment, 'json', 'unused default', 'array');
    }
}

final class ScalarConversionStringValue implements Stringable
{
    public function __toString(): string
    {
        return '42';
    }
}
